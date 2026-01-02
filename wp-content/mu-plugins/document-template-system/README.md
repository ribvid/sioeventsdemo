# Document Template System - Proof of Concept

Multi-tenant document generation system for WordPress with organization-scoped templates.

## Features

- **Organization Management**: Create and manage multiple organizations
- **Multi-Tenancy**: Users are assigned to organizations and can only access their organization's templates
- **Template Library**: Upload Word (.docx) templates with placeholders
- **Document Generation**: Auto-generate PDFs from templates on form submission
- **Attendance Lists**: Generate attendance sheets from multiple form entries
- **QR Code Integration**: Automatically embed QR codes in generated documents
- **Admin Override**: Administrators can manage all organizations and templates

## Installation

1. The plugin is already installed in `wp-content/mu-plugins/document-template-system/`
2. Must-use plugins load automatically - no activation needed
3. Ensure ACF Pro is installed and active

## Quick Start Guide

### Step 1: Create Organizations

1. Go to **Organizacije** in WordPress admin
2. Click **Dodaj organizacijo**
3. Enter organization name and contact details
4. Publish the organization

### Step 2: Assign Users to Organizations

1. Go to **Users** → **All Users**
2. Edit a user
3. Scroll down to **Organizacija** field
4. Select the organization for this user
5. Update user

### Step 3: Create Document Templates

1. Go to **Predloge dokumentov**
2. Click **Dodaj predlogo**
3. Enter template name
4. Upload a .docx file with placeholders:
   - `${ime_udeleženca}` - First name
   - `${priimek_udeleženca}` - Last name
   - `${email_udeleženca}` - Email
   - `${qr_koda}` - QR code image
   - `${id_prijave}` - Entry ID
   - `${datum_prijave}` - Registration date
5. Select template type (Vstopnica, Potrdilo, or Podpisni list)
6. Select organization
7. Publish template

**For Attendance Lists**:
Create a Word table with placeholders in the first row:
- `${row}` - Row number
- `${ime}` - First name
- `${priimek}` - Last name
- `${email}` - Email

The row will be automatically duplicated for each registered attendee.

### Step 4: Select Templates for Course Sessions

1. Go to **Izvedbe izobraževanj**
2. Edit a course session
3. Scroll to **Predloge dokumentov** section
4. Select templates for:
   - Vstopnica (Ticket)
   - Potrdilo (Certificate)
   - Podpisni list (Attendance List)
5. Update course session

### Step 5: Test Document Generation

**Tickets/Certificates** (auto-generated on form submission):
1. Submit a Gravity Form entry for the course session
2. PDF is automatically generated and emailed to the participant

**Attendance Lists** (manual download):
1. Edit the course session
2. Scroll to **Predloge dokumentov**
3. Click **Prenesi podpisni list (PDF)**
4. PDF downloads automatically

## Access Control

### Non-Admin Users
- See only their assigned organization
- See only templates belonging to their organization
- Cannot access other organizations' data

### Administrators
- See all organizations
- See all templates
- Can manage all data across organizations

## File Structure

```
wp-content/mu-plugins/document-template-system/
├── document-template-system.php       # Main plugin file
├── README.md                          # This file
├── includes/
│   ├── cpt-organization.php           # Organization CPT
│   ├── cpt-document-template.php      # Template CPT
│   ├── access-control.php             # Multi-tenancy logic
│   ├── template-generator.php         # PDF generation
│   └── acf-fields.php                 # Field definitions
└── acf-json/                          # ACF field exports (auto-generated)
```

## Placeholders Reference

### Available in All Templates
- `${ime_udeleženca}` - Participant first name
- `${priimek_udeleženca}` - Participant last name
- `${email_udeleženca}` - Participant email
- `${id_prijave}` - Registration ID
- `${datum_prijave}` - Registration date
- `${qr_koda}` - QR code for attendance tracking

### Attendance List Specific
- `${row}` - Row number (auto-incrementing)
- `${ime}` - First name
- `${priimek}` - Last name
- `${email}` - Email

### Custom Placeholders
Add custom placeholders via the **placeholders** repeater field on course_session.

## Technical Details

### Dependencies
- WordPress 5.0+
- ACF Pro
- Gravity Forms
- PhpOffice/PhpWord (loaded via theme)
- Dompdf (loaded via theme)
- Endroid/QrCode (loaded via theme)

### Hooks
- `gform_after_submission` (priority 5) - Ticket generation
- `acf/save_post` (priority 20) - Attendance list download
- `pre_get_posts` - Query filtering for multi-tenancy
- `acf/fields/post_object/query` - Template selection filtering

### File Locations
- Tickets: `/wp-content/uploads/gravity-forms-pdfs/`
- Attendance Lists: `/wp-content/uploads/attendance-lists/`

## Testing Checklist

- [ ] Create 2 organizations
- [ ] Create 2 users, assign to different organizations
- [ ] Verify User A only sees Organization A
- [ ] Upload .docx template for each organization
- [ ] Verify templates are filtered by organization
- [ ] Create course_session, select template
- [ ] Submit Gravity Form entry
- [ ] Verify ticket PDF generated and emailed
- [ ] Create attendance list with multiple entries
- [ ] Verify attendance list PDF downloads correctly
- [ ] Verify admin can see all organizations and templates

## Security Features

- File upload restricted to .docx only
- Template access controlled via organization assignment
- Admin capabilities checked for full access
- File paths validated before processing
- User organization verified before template selection
- Query filtering at database level

## Troubleshooting

### Templates not showing in dropdown
- Check that user has an organization assigned
- Verify template type matches field (ticket/certificate/attendance_list)
- Confirm template is assigned to user's organization

### PDF not generating
- Check WordPress debug.log for errors
- Verify .docx template file exists and is valid
- Ensure placeholders match exactly (case-sensitive)
- Check file permissions on upload directories

### Access denied errors
- Verify user has an organization assigned
- Check user role (Editor level required for non-admins)
- Confirm organization assignment is saved

## Future Enhancements

- Email template customization per organization
- Bulk template import/export
- Template preview functionality
- Usage analytics per organization
- Custom placeholder builder UI
- Multi-language support for generated documents

## Support

For issues or questions, contact the development team or check the implementation plan in:
`C:\Users\peter\.claude\plans\humble-petting-raccoon.md`

## Version

1.0.0 - Initial proof of concept release
