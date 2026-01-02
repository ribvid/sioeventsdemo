# Word Template Creation Guide

## How to Create Templates for the Document Generation System

### Basic Concepts

The system uses **placeholders** in your Word documents. Placeholders are special text markers that get replaced with actual data when generating PDFs.

Placeholder format: `${placeholder_name}`

### Creating a Ticket Template

1. Open Microsoft Word or LibreOffice Writer
2. Design your ticket layout
3. Insert placeholders where you want dynamic content:

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
         VSTOPNICA / TICKET
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Ime in priimek:
${ime_udeleženca} ${priimek_udeleženca}

Email: ${email_udeleženca}

Številka prijave: ${id_prijave}
Datum prijave: ${datum_prijave}

QR koda za preverjanje:
${qr_koda}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

4. Save as `.docx` format (not .doc or .pdf)
5. Upload to **Predloge dokumentov** in WordPress admin

### Creating an Attendance List Template

Attendance lists use a **table** with special row cloning.

1. Open Microsoft Word
2. Create a table with headers
3. In the **second row** (data row), use these placeholders:

| Št. | Ime | Priimek | Email | Podpis |
|-----|-----|---------|-------|--------|
| ${row} | ${ime} | ${priimek} | ${email} |  |

4. The system will automatically:
   - Clone the second row for each registered participant
   - Fill in data for each person
   - Number rows automatically

5. Save as `.docx` and upload

### Available Placeholders

#### For Tickets & Certificates

| Placeholder | Description | Example Output |
|-------------|-------------|----------------|
| `${ime_udeleženca}` | First name | Janez |
| `${priimek_udeleženca}` | Last name | Novak |
| `${email_udeleženca}` | Email address | janez.novak@example.com |
| `${id_prijave}` | Registration ID | 123 |
| `${datum_prijave}` | Registration date | 2025-01-15 10:30:25 |
| `${qr_koda}` | QR code image | (image) |

#### For Attendance Lists Only

| Placeholder | Description | Example Output |
|-------------|-------------|----------------|
| `${row}` | Row number | 1, 2, 3... |
| `${ime}` | First name | Janez |
| `${priimek}` | Last name | Novak |
| `${email}` | Email | janez.novak@example.com |

#### Custom Placeholders

You can add custom placeholders via the **placeholders** repeater field on the course_session edit page.

Example:
- Placeholder: `${naziv_izobrazevanja}`
- Value: "Spletno programiranje"

Then use `${naziv_izobrazevanja}` in your template.

### Best Practices

#### 1. Test Your Template

- Create a test organization
- Upload your template
- Create a test course_session
- Submit a test form entry
- Check the generated PDF

#### 2. Font Selection

- Use standard fonts: Arial, Calibri, Times New Roman
- DejaVu Sans is used by the PDF converter (best for Slovenian characters)

#### 3. Image Placeholders

- For QR codes, leave enough space: at least 150x150px
- Use `${qr_koda}` placeholder where the QR should appear
- The image will be automatically embedded

#### 4. Table Formatting

- Keep table design simple
- Don't merge cells in the data row (row to be cloned)
- Use borders and shading for better readability

#### 5. File Size

- Keep templates under 2MB
- Compress images before inserting
- Avoid embedded fonts

### Common Issues & Solutions

#### Placeholder Not Replaced

**Problem**: Text `${ime_udeleženca}` appears in PDF instead of actual name

**Solutions**:
- Check spelling (case-sensitive)
- Ensure placeholder is exactly `${ime_udeleženca}` (with $ and {})
- No spaces inside the placeholder
- Use plain text formatting (not styled text)

#### QR Code Not Appearing

**Problem**: QR code placeholder remains as text

**Solutions**:
- Use exactly `${qr_koda}` (lowercase, Slovenian spelling)
- Ensure placeholder is on its own line or in a table cell
- Leave adequate space for the image

#### Attendance List Not Cloning Rows

**Problem**: Only one row appears, or errors occur

**Solutions**:
- Use placeholders in the **second row** of the table, not the header
- Ensure at least one placeholder from the list exists: `${row}`, `${ime}`, `${priimek}`, `${email}`
- Don't merge cells in the data row

#### Slovenian Characters Broken (č, š, ž)

**Problem**: Characters appear as ???

**Solutions**:
- Save Word file as .docx (not .doc)
- Use UTF-8 encoding
- Standard fonts work better than custom fonts

### Template Examples

#### Minimal Ticket Template

```
VSTOPNICA

${ime_udeleženca} ${priimek_udeleženca}
${email_udeleženca}

${qr_koda}

Številka: ${id_prijave}
```

#### Professional Certificate Template

```
═══════════════════════════════════════════════
           POTRDILO O UDELEŽBI
           CERTIFICATE OF ATTENDANCE
═══════════════════════════════════════════════

Potrjujemo, da je

    ${ime_udeleženca} ${priimek_udeleženca}

uspešno zaključil/a izobraževanje.

Datum: ${datum_prijave}
ID: ${id_prijave}

                                    [Podpis]
```

#### Attendance List Template

```
SEZNAM UDELEŽENCEV / ATTENDANCE LIST

╔═══╦═══════════╦════════════╦═══════════════════════╦══════════╗
║ Št║ Ime       ║ Priimek    ║ Email                 ║ Podpis   ║
╠═══╬═══════════╬════════════╬═══════════════════════╬══════════╣
║${row}║${ime}║${priimek}║${email}║          ║
╚═══╩═══════════╩════════════╩═══════════════════════╩══════════╝
```

### Advanced: Conditional Content

Word templates support basic conditional logic using field codes, but for the POC, we recommend keeping it simple with direct placeholder replacement.

For complex needs:
1. Create multiple templates (e.g., "Ticket - VIP", "Ticket - Standard")
2. Select the appropriate template per course_session
3. Use custom placeholders for variable content

### File Naming Conventions

Recommended naming:
- `vstopnica-osnovna-sola.docx`
- `potrdilo-arnes-2025.docx`
- `podpisni-list-tabla.docx`

This helps identify templates quickly in the admin panel.

### Testing Checklist

- [ ] All placeholders spelled correctly
- [ ] QR code placeholder present (if needed)
- [ ] Table structure correct (for attendance lists)
- [ ] Slovenian characters display correctly
- [ ] File saved as .docx
- [ ] File size under 2MB
- [ ] Template assigned to correct organization
- [ ] Template type selected correctly

### Getting Help

If you encounter issues:
1. Check this guide for common problems
2. Verify template in WordPress admin
3. Check debug.log for error messages
4. Contact administrator for assistance

## Version

1.0.0 - Initial template guide for POC
