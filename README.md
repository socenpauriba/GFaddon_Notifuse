# Gravity Forms Notifuse Add-On

WordPress plugin that integrates Gravity Forms with Notifuse to automatically sync contacts and subscriptions.

## Description

This add-on allows you to connect Gravity Forms with your Notifuse instance. When a user submits a form, their data is automatically synced with Notifuse and can be subscribed to specific lists.

## Requirements

- WordPress 5.0 or higher
- Gravity Forms 1.9.16 or higher
- PHP 7.0 or higher
- Notifuse account with API token

## Installation

1. Upload the `GFaddon_Notifuse` folder to the `/wp-content/plugins/` directory
2. Activate the plugin from the WordPress 'Plugins' menu
3. Go to **Forms > Settings > Notifuse** to configure the connection

## Configuration

### 1. Global Configuration

At **Forms > Settings > Notifuse**, configure:

- **Notifuse Instance URL**: Your instance URL (ex: `https://demo.notifuse.com`)
- **API Token**: Your Notifuse API authentication token
- **Workspace ID**: Your workspace identifier (ex: `ws_1234567890`)

### 2. Feed Configuration

For each form you want to connect with Notifuse:

1. Go to **Forms > [Your Form] > Settings > Notifuse**
2. Click **Add New**
3. Configure:
   - **Feed Name**: A descriptive name to identify this feed (ex: "Main Newsletter")
   - **Subscription Lists**: List names separated by commas (ex: `newsletter, product_updates`)
   - **Field Mapping**: 
     - Email ← Select the email field from the form
     - First Name ← Select the first name field
     - Last Name ← Select the last name field
     - *(Leave other fields blank if you don't need them)*
   - **Custom Fields** (optional):
     - Add fields like `custom_string_1` if you need to send additional data
   - **Condition** (optional): Define conditions to process the feed

## Field Mapping

The plugin displays a complete list of Notifuse fields and for **each Notifuse field** you can select which field from your form you want to send.

### Available standard fields:

| Notifuse Field | Description | Required |
|---------------|------------|------------|
| **email** | Contact email address | ✅ Yes |
| **external_id** | Unique identifier from your system | No |
| **first_name** | Contact first name | No |
| **last_name** | Contact last name | No |
| **phone** | Phone number | No |
| **timezone** | ISO timezone (ex: Europe/Madrid) | No |
| **language** | Preferred language | No |
| **job_title** | Professional title | No |
| **address_line_1** | Primary address | No |
| **address_line_2** | Secondary address (apt, suite, etc.) | No |
| **country** | Country in ISO format (ex: ES, US, FR) | No |
| **state** | State or province | No |
| **postcode** | Postal or ZIP code | No |

### Custom fields:

In addition to standard fields, you can add **custom fields** from Notifuse:

- **custom_string_1** to **custom_string_5** - Custom text fields
- **custom_number_1** to **custom_number_5** - Custom numeric fields
- **custom_datetime_1** to **custom_datetime_5** - Custom date/time fields
- **custom_json_1** to **custom_json_5** - Custom JSON fields

To add custom fields:
1. In the "Custom Fields" section click **"Add Field"**
2. Enter the exact Notifuse field name (ex: `custom_string_1`)
3. Select the form field you want to send

### How mapping works

1. **Standard fields**: All main Notifuse fields are displayed. Select the ones you need.
2. **Email required**: Only the email field is required, the rest are optional.
3. **Empty fields**: Fields you don't map simply won't be sent to Notifuse.
4. **Empty values**: Only fields with values are sent (empty fields are not processed).
5. **Custom fields**: You can add as many custom fields as you need.

## Features

✅ **Automatic sync**: Contacts are automatically sent when the form is submitted
✅ **Flexible mapping**: Map any form field to Notifuse fields
✅ **List subscription**: Automatically subscribe contacts to multiple lists
✅ **Conditions**: Define conditions to control when information is sent
✅ **Delayed payment support**: Compatible with Gravity Forms payments
✅ **Error management**: Logs errors and adds notes to entries
✅ **Validation**: Validates URLs and required fields
✅ **Multi-language**: Available in English, Catalan and Spanish

## API Functionality

The plugin uses the Notifuse endpoint:

```
POST https://[instance].notifuse.com/api/contacts.import
```

With the following data format:

```json
{
  "workspace_id": "ws_1234567890",
  "contacts": [
    {
      "email": "user@example.com",
      "first_name": "John",
      "last_name": "Doe"
    }
  ],
  "subscribe_to_lists": ["newsletter", "product_updates"]
}
```

## Technical Notes

- The plugin uses the Gravity Forms `GFFeedAddOn` framework
- API requests are made with `wp_remote_request()`
- Request timeout is 30 seconds
- Success or errors are logged as notes in Gravity Forms entries

## Supported Languages

The plugin is available in:

- 🇬🇧 **English** (default)
- 🇪🇸 **Catalan** (`ca`)
- 🇪🇸 **Spanish** (`es_ES`)

### Adding New Translations

If you want to add a new translation:

1. Copy the `languages/notifuse-ca.po` file
2. Rename it with the language code (ex: `notifuse-fr_FR.po` for French)
3. Translate the texts
4. Compile the .po file to .mo with a tool like [Poedit](https://poedit.net/)

## Project Structure

```
GFaddon_Notifuse/
├── notifuse.php                     # Main plugin file (bootstrap)
├── class-gfnotifuseaddon.php        # Main class with all the logic
├── css/
│   └── my_styles.css                # Admin styles
├── js/
│   └── my_script.js                 # JavaScript scripts (reserved)
├── languages/
│   ├── notifuse-ca.po               # Catalan translation
│   └── notifuse-es_ES.po            # Spanish translation
└── README.md                        # Plugin documentation
```

## License

GNU General Public License v2.0 or higher
