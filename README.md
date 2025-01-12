# Magento 2 Netgsm IYS Integration

This module provides integration with Netgsm IYS (İleti Yönetim Sistemi) for managing commercial electronic message permissions in Magento 2.

## Features

- Sync customer permissions with Netgsm IYS
- Webhook integration for real-time updates
- Admin grid for permission management
- Detailed logging system
- Automatic customer association
- Batch processing support
- Multiple message types support (SMS, Call, Email)

## Installation

1. Create directory for the module:
```
mkdir -p app/code/IDangerous/NetgsmIYS
```

2. Copy module files to the directory

3. Enable the module:
```
php bin/magento module:enable IDangerous_NetgsmIYS
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
```

## Configuration

1. Go to Admin > Stores > Configuration > iDangerous > Netgsm IYS Settings
2. Configure the following:
   - Username (Netgsm account username)
   - Password (Netgsm account password)
   - Brand Code
   - App Key (optional)
   - Webhook Token (for secure webhook communication)
   - Webhook Allowed Hosts (optional)
   - Enable Logging

## Usage

### Admin Grid
- Access the IYS records grid at: Admin > Marketing > Netgsm IYS > IYS Records
- View detailed information about each record
- Filter and sort records
- View sync history and API responses

### Manual Sync
```
# Sync all pending records
php bin/magento netgsm:iys:sync

# Sync specific record
php bin/magento netgsm:iys:sync --id=123

# Debug mode
php bin/magento netgsm:iys:sync --debug
```

### Webhook Integration
Webhook URL: `https://your-domain.com/netgsm/iys/webhook`

Example webhook payload:
```
{
    "iyscode": "123456",
    "brandcode": "123456",
    "type": "ARAMA",
    "source": "HS_WEB",
    "status": "ONAY",
    "consentdate": "2024-01-08 13:55:00",
    "recipienttype": "BIREYSEL",
    "recipient": "+905320111110"
}
```

### Cron Jobs
The module includes automatic synchronization via cron:
- Job code: `netgsm_iys_sync`
- Default schedule: Every 5 minutes
- Configurable batch size

## Database Tables

### iys_data
- `id` - Record ID
- `type` - Message type (sms, call, email)
- `value` - Phone number or email
- `status` - Permission status
- `userid` - Associated customer ID
- `modified` - Last modification date
- `created` - Creation date
- `iys_status` - Sync status with IYS
- `last_iys_result` - Last API response

## Status Codes

### Permission Status
- 0: Not Set
- 1: Accepted
- 2: User Rejected
- 3: IYS Rejected

### IYS Sync Status
- 0: Pending
- 1: Synced

## Logging

Logs are stored in:
- `var/log/netgsm_iys/*.log`

Enable debug logging in admin configuration for detailed information.

## CSV Import

### Command Line Import
```bash
# Import from CSV file
php bin/magento idangerous:iys:import --file=/path/to/your/file.csv
```

### CSV Format
The CSV file should have the following columns:
```csv
type,value,status,userid,modified (header must be exists)
sms,+905321234567,1,1,2024-01-01 00:00:00
email,test@example.com,1,2,2024-01-01 00:00:00
```

#### Column Descriptions
- `type`: Message type (sms, email, call)
- `value`: Phone number or email address
- `status`: Permission status (0=Not Set, 1=Accepted, 2=User Rejected, 3=IYS Rejected)
- `userid`: Customer ID (optional)
- `modified`: Last modification date (YYYY-MM-DD HH:mm:ss)

### Admin Import
1. Go to Admin > Stores > Configuration > iDangerous > Netgsm IYS Settings
2. Navigate to the CSV Import Settings section
3. Download the sample file for reference
4. Prepare your CSV file following the sample format
5. Use the import button to upload and process your file

### Import Notes
- Empty values will be skipped
- Existing records will be updated based on the value field
- Records are marked for IYS sync after import
- Import progress and results are logged
- Command line import provides detailed progress output

## Support

For issues and feature requests, please create an issue in the repository.

## License

[MIT License](LICENSE.md)

[Developer: Orkan Köylü](orkan.koylu@gmail.com)