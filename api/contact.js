// Updated serverless contact handler with robust attachment parsing
// (handles input names: "files", "files[]", "file", "upload")
//  [oai_citation:0‡contact.js](sediment://file_00000000ed3c62309c6774aafa38c5a5)
import nodemailer from 'nodemailer';
import formidable from 'formidable';

export const config = { api: { bodyParser: false } };

function parseForm(req) {
    const form = formidable({ multiples: true, keepExtensions: true });
    return new Promise((resolve, reject) => {
        form.parse(req, (err, fields, files) => (err ? reject(err) : resolve({ fields, files })));
    });
}

// Pick an array of files regardless of the field name/shape
function pickFileArray(filesObj) {
    if (!filesObj || typeof filesObj !== 'object') return [];
    // Try common field names
    const possibleKeys = ['files', 'files[]', 'file', 'upload', 'attachment', 'attachments'];
    const key = Object.keys(filesObj).find(k => possibleKeys.includes(k)) || Object.keys(filesObj)[0];
    const raw = key ? filesObj[key] : undefined;
    return Array.isArray(raw) ? raw : raw ? [raw] : [];
}

export default async function handler(req, res) {
    if (req.method !== 'POST') return res.status(405).json({ ok: false, error: 'Method not allowed' });

    try {
        const { fields, files } = await parseForm(req);

        const get = (k) => (fields[k] ?? '').toString().trim();
        const name = get('name');
        const phone = get('phone');
        const treat = get('requested_treatment');
        const email = get('email');

        if (!name || !phone || !treat) {
            return res.status(400).json({ ok: false, error: 'Name, Phone, and Requested treatment are required.' });
        }

        const esc = (s) => String(s || '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
        const cell = (k, v) => `<tr><td style="background:#f7f9fb;width:40%;font-weight:600;border:1px solid #e8edf3;">${k}</td><td style="border:1px solid #e8edf3;">${v}</td></tr>`;

        const rows = [
            cell('Name', esc(name)),
            email ? cell('Email', esc(email)) : '',
            cell('Phone', esc(phone)),
            cell('Requested Treatment', esc(treat)),

            fields.chronic ? cell('Chronic / Meds / HbA1c', esc(fields.chronic)) : '',
            fields.oral_issues ? cell('Oral pain / bleeding / gum disease', esc(fields.oral_issues)) : '',
            fields.mobile_teeth ? cell('Wobbly teeth', esc(fields.mobile_teeth)) : '',
            fields.missing_duration ? cell('Missing teeth duration', esc(fields.missing_duration)) : '',
            fields.smoking ? cell('Smoking', esc(fields.smoking)) : '',
            fields.legal_name ? cell('Full name for plan', esc(fields.legal_name)) : '',
            fields.age ? cell('Age', esc(fields.age)) : '',
            fields.travel_dates ? cell('Travel dates', esc(fields.travel_dates)) : '',
            fields.city ? cell('City', esc(fields.city)) : '',
            fields.postcode ? cell('Postcode', esc(fields.postcode)) : '',
            fields.departure_airport ? cell('Departure airport', esc(fields.departure_airport)) : '',
            fields.medications ? cell('Medications & dose', esc(fields.medications)) : '',
            fields.medical_conditions ? cell('Medical conditions', esc(fields.medical_conditions)) : '',
            fields.allergies ? cell('Allergies', esc(fields.allergies)) : '',
            fields.last_gp ? cell('Last GP appointment', esc(fields.last_gp)) : '',
            fields.last_blood_test ? cell('Last blood test', esc(fields.last_blood_test)) : '',
            fields.surgeries ? cell('Recent surgeries', esc(fields.surgeries)) : '',
            fields.insurance ? cell('Insurance', esc(fields.insurance)) : ''
        ].join('');

        const html = `<html><body>
      <table rules="all" style="border:1px solid #666;border-collapse:collapse;width:100%;max-width:640px" cellpadding="10">
        ${rows}
      </table>
    </body></html>`;

        // Build attachments robustly (supports files, files[], file, upload, etc.)
        const fileArray = pickFileArray(files);
        const attachments = fileArray.map(f => ({
            filename: f.originalFilename || f.newFilename || 'attachment',
            path: f.filepath, // Vercel tmp path works for nodemailer
            contentType: f.mimetype || 'application/octet-stream'
            // Alternative if needed:
            // content: fs.createReadStream(f.filepath)
        }));

        const transporter = nodemailer.createTransport({
            host: 'smtp.gmail.com',
            port: 587,
            secure: false,
            auth: { user: process.env.SMTP_USER, pass: process.env.SMTP_PASS }
        });

        await transporter.sendMail({
            from: `"Turkiye Dental Website" <${process.env.SMTP_USER}>`,
            to: process.env.RECIPIENT || process.env.SMTP_USER,
            replyTo: email ? `${name} <${email}>` : undefined,
            subject: `New Second-Opinion Request — ${treat} — ${name}`,
            html,
            attachments
        });

        return res.status(200).json({ ok: true, message: 'Sent' });
    } catch (err) {
        console.error(err);
        return res.status(500).json({ ok: false, error: 'Server error' });
    }
}