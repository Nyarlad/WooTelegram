<?php

if (!defined('ABSPATH')) {
    exit;
}

class Telegram extends WC_Integration
{

    const API_TELEGRAM = 'https://api.telegram.org/bot';

    public $registerWebhook;

    public $token;

    public $chatId;

    public $userId;

    public function __construct()
    {
        $current_user = wp_get_current_user();
        $this->userId = $current_user->ID;

        $this->id                 = "woo-telegram";
        $this->method_title       = "Телеграм бот для WooCommerce";
        $this->method_description = "Плагин связывает WooCommerce c Telegram ботом.";
        $this->init_form_fields();
        $this->init_settings();

        $this->token  = $this->settings['token'];
        $this->chatId = get_user_meta($this->userId, 'telegram', true);
        $this->registerWebhook = $this->checkExistsWebhook($this->token);
        
        add_action('woocommerce_api_woo-telegram',
          [$this, 'wooTelegramResponse']);
        add_action("woocommerce_update_options_integration_" . $this->id,
          [$this, "process_admin_options"]);
        add_action('woocommerce_checkout_order_processed',
          [$this, 'sendOrderToTelegram'], 10, 3);
    }

    public function init_form_fields()
    {
        $this->form_fields = [
          "token" => [
            "title"       => "Токен Telegram",
            "description" => "Введите token полученный от BotFather",
            "type"        => "text",
            "class"       => "tm-token",
            "desc_tip"    => true,
            "default"     => get_option("token"),
          ],
        ];
    }

    public function process_admin_options()
    {
        $result      = parent::process_admin_options();
        $this->token = $this->settings['token'];
        $this->setTelegramWebhook();
        $this->registerWebhook = $this->checkExistsWebhook($this->token);
        return $result;
    }

    function admin_options()
    {
        $hash = md5('telegram' . $this->userId);

        echo '<table class="form-table">';
        echo $this->generate_settings_html($this->form_fields, false);
        echo '</table>';
        if (!$this->registerWebhook) {
            echo '<a href="#" class="button-secondary" id="webhook">WebHook не зарегистрирован</a>';
        }
        if (empty($this->chatId)) {
            echo "<p> Найдите нашего бота @woo_telegram_bot нажмите Начать и напишите ему команду /key=$this->userId&auth=$hash долждитесь ответа бота и перегрузите эту страницу </p>";
        } else {
            echo "<p>Номер telegram чата : $this->chatId </p>";
        }
        $this->display_errors();
    }

    public function validate_text_field($key, $value)
    {
        if ($key == 'token') {
            if (!$this->checkToken($value)) {
                $this->add_error('Токен не существует');
            }
        }
        return parent::validate_text_field($key, $value);
    }

    private function setTelegramWebhook()
    {
        $logger = wc_get_logger();
        $url    = self::API_TELEGRAM . $this->token . '/setWebhook';
        $logger->info(wc_print_r($url, true));
        $args     = [
          'timeout'     => 5,
          'redirection' => 1,
          'httpversion' => '1.0',
          'blocking'    => true,
          'headers'     => ['Content-Type' => 'application/x-www-form-urlencoded'],
          'body'        => ['url' => home_url('/?wc-api=woo-telegram')],
        ];
        $response = wp_remote_post($url, $args);
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $logger->info(wc_print_r($error_message, true));
        }
    }

    private function checkExistsWebhook(string $token)
    {
        $logger = wc_get_logger();
        $url    = self::API_TELEGRAM . $token . '/getWebhookInfo';
        $logger->info(wc_print_r($url, true));
        $response = wp_remote_get($url);
        $body     = wp_remote_retrieve_body($response);
        if (!empty($body)) {
            try {
                $data = json_decode($body, true);
                if (!empty($data['result']['url'])) {
                    return true;
                }
            } catch (Exception $e) {
            }
        }
        return false;
    }

    private function checkToken(string $token)
    {
        $url      = self::API_TELEGRAM . $token . '/getMe';
        $response = wp_remote_get($url);
        $body     = wp_remote_retrieve_body($response);
        if (!empty($body)) {
            try {
                $data = json_decode($body, true);
                if (!empty($data['result']['username'])) {
                    return true;
                }
            } catch (Exception $e) {
            }
        }
        return false;
    }

    public function wooTelegramResponse()
    {
        global $woocommerce;
        $data   = file_get_contents("php://input");
        $logger = wc_get_logger();
        try {
            $result = $this->decodePost($data);
            $userId = $this->parseText($result['text']);
            if (update_user_meta($userId, 'telegram', $result['chatId'])) {
                $eol  = PHP_EOL;
                $text = 'Добро пожаловать в WooCommerce.' . $eol;
                $text .= 'На странице плагина Вы должны увидеть номер Вашего чата ' . $result['chatId'] . $eol;
                $text .= 'Спасибо !';
                $this->sendMessageToTelegram($text, $result['chatId'],
                  $this->token);
            } else {
                if ($chatId = get_user_meta($userId, 'telegram', true)) {
                    $text = 'Вы уже зарегистрированы в WooCommerce.' . PHP_EOL;
                    $text .= 'Спасибо за то, что Вы с нами';
                    $this->sendMessageToTelegram($text, $chatId, $this->token);
                }
            }
        } catch (Exception $e) {
            $logger->info(wc_print_r($e->getMessage(), true));
        }
    }

    /**
     * @param string $text
     *
     * @return bool|mixed
     * @throws \Exception
     */
    private function parseText(string $text)
    {
        $input = [];
        parse_str($text, $input);
        $userId = empty($input['key']) ? false : $input['key'];
        $hash   = empty($input['auth']) ? false : $input['auth'];
        if ($userId && $hash && $hash == md5('telegram' . $userId)) {
            return $userId;
        }
        throw new Exception('Не найден пользователь или не совпал секрет !');
    }

    /**
     * @param string $post
     *
     * @return array
     * @throws \Exception
     */
    private function decodePost(string $post): array
    {
        $data   = json_decode($post, true);
        $text   = empty($data['message']['text']) ? false : $data['message']['text'];
        $text   = substr($text, 1);
        $chatId = empty($data['message']['chat']['id']) ? false : $data['message']['chat']['id'];
        if ($text && $chatId) {
            return [
              'text'   => $text,
              'chatId' => $chatId,
            ];
        }
        throw new Exception('Не хватает аргументов text или chatId');
    }


    public function sendMessageToTelegram(
      string $text,
      string $chatId,
      string $token
    ): void {
        $url      = self::API_TELEGRAM . $token . '/sendMessage';
        $args     = [
          'timeout'     => 5,
          'redirection' => 1,
          'httpversion' => '1.0',
          'blocking'    => true,
          'headers'     => ['Content-Type' => 'application/x-www-form-urlencoded'],
          'body'        => ['text' => $text, 'chat_id' => $chatId],
        ];
        $response = wp_remote_post($url, $args);
        $logger   = wc_get_logger();
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $logger->info(wc_print_r($error_message, true));
        }
    }

    public function sendOrderToTelegram($order_id, $posted, $order)
    {
        $text = ' Заказ № ' . $order->get_order_number() . PHP_EOL;
        $text .= 'Клиент: ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() . PHP_EOL;
        $text .= 'По адресу: ' . $order->get_billing_address_1() . ' ' . $order->get_billing_postcode() . PHP_EOL;
        $text .= 'Телефон: ' . $order->get_billing_phone() . PHP_EOL;
        $text .= 'Email: ' . $order->get_billing_email() . PHP_EOL;
        $text .= 'Сумма заказа: ' . $order->get_total() . PHP_EOL;


        $text  .= 'Содержимое заказа :' . PHP_EOL;
        $items = $order->get_items();
        $item_count = 1;
        $enter = '
';
        foreach ($items as $item) {
            $product = $item->get_product();
            $format_product1 = str_replace("<span>", '', $product->get_name());
            $format_product2 = str_replace("</span>", '', $format_product1);
            $format_product3 = str_replace(" - ", $enter . 'Цвет: ' . $enter , $format_product2);
            $qty     = $item->get_quantity() ? $item->get_quantity() : 1;
            $price   = wc_format_localized_price($item->get_total() / $qty);
            $text    .= '                    Товар ' . $item_count . ': ' . $enter . $format_product3 . $enter . 'Кол-во: ' . $qty . $enter . 'Цена: ' . $price . $enter;
            $item_count++;
        }

        foreach ($this->getTelegramUsers() as $user) {
            $chatId = get_user_meta($user->ID, 'telegram', true);
            $this->sendMessageToTelegram($text, $chatId, $this->token);
        }
    }

    private function getTelegramUsers()
    {
        return get_users([
          'meta_key' => 'telegram',
        ]);
    }
}