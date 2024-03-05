## WooCommerce Telegram Bot Integration Plugin

This plugin enables seamless integration of Telegram bots into WooCommerce, allowing for the automatic sending of order information to managers or administration.

### Getting Started

1. **Creating a Telegram Bot:**
   - Start by creating a new bot using the @BotFather bot.
   - Open @BotFather and enter the `/newbot` command.
   - Provide a Title and Name for the bot. Ensure to copy the API generated by BotFather for future integration.

2. **Adding the Plugin to WordPress:**
   - Download the files from this repository and zip the `WooTelegramEdit` folder into a zip archive.
   - Navigate to WordPress, go to `Plugins` > `Add New`, and click the "Upload Plugin" button.
   - Upload the plugin zip archive.
   - Wait for the installation to complete, then click 'Activate plugin'.

3. **Bot Integration:**
   - In WordPress, go to `WooCommerce` > `Settings` > `Integration` > `Telegram bot for WooCommerce`.
   - Paste the copied bot API into the designated field and click "Save changes".
   - Copy the key provided and send it in a private message to the bot in Telegram.

### Usage

- Each WordPress administrator now has an individual key on the plugin pages to facilitate integration.
- Note: Each key is unique and only works for one user.
