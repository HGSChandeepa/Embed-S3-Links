# S3_links - Moodle Plugin

## Description
S3_links is a Moodle repository plugin that enables direct access to Amazon S3 stored files using region-specific URLs. This plugin modifies the standard S3 repository plugin to generate direct S3 URLs in the format `https://bucket-name.s3.region.amazonaws.com/object-key` instead of pre-signed URLs.

## Features
- Direct S3 URL generation for public files
- Region-aware URL construction
- Supports all AWS regions
- Compatible with public S3 buckets
- Easy integration with existing Moodle installations
- Maintains all standard S3 repository functionality

## Requirements
- Moodle 4.5 or higher
- PHP 8.2 or higher
- Active AWS Account with S3 bucket
- S3 bucket configured for public access
- AWS Access Key and Secret Key

## Installation
1. Download the plugin files
2. Place the files in: `/repository/s3_links/`
3. Visit your Moodle site's administration area
4. Follow the plugin installation prompts
5. Configure the plugin with your AWS credentials

## Configuration
1. Navigate to Site administration > Plugins > Repositories > S3_links
2. Enter your AWS credentials:
   - Access Key
   - Secret Key
   - Select your S3 endpoint from the dropdown
3. Save changes

## AWS S3 Bucket Setup
1. Create or select an S3 bucket
2. Configure bucket for public access:
   ```json
   {
       "Version": "2012-10-17",
       "Statement": [
           {
               "Sid": "PublicReadGetObject",
               "Effect": "Allow",
               "Principal": "*",
               "Action": "s3:GetObject",
               "Resource": "arn:aws:s3:::your-bucket-name/*"
           }
       ]
   }
   ```
3. Enable public access settings as needed

## Usage
1. In Moodle's file picker, select "S3_links" repository
2. Browse your S3 buckets and files
3. Select files to generate direct S3 URLs
4. URLs will be in format: `https://bucket-name.s3.region.amazonaws.com/object-key`

## Troubleshooting
- Ensure your S3 bucket has proper public access configuration
- Verify AWS credentials are correct
- Check if the selected endpoint matches your bucket's region
- Confirm file permissions in S3 are set to public
- Verify the bucket policy allows public read access

## Security Considerations
- This plugin generates direct URLs without signing
- Only use with content intended to be public
- Keep AWS credentials secure
- Regularly rotate AWS access keys
- Monitor S3 access logs
- Review bucket policies periodically

## Contributing
Contributions are welcome! Please:
1. Fork the repository
2. Create a feature branch
3. Submit a pull request

## License
GNU GPL v3 or later
http://www.gnu.org/copyleft/gpl.html

## Author
[Samin Chandeepa/@GeekLabs]

## Support
For support:
- Create an issue on GitHub
- Contact the plugin maintainer
- Check Moodle forums

## Changelog
### Version 1.0.0 (Initial Release)
- Initial release with direct URL support
- Region-aware URL generation
- Basic repository functionality
- Public bucket access support
