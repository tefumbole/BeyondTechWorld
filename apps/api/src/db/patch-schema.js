/**
 * Idempotent ALTER patches for databases created from an older schema.sql.
 */
export const SCHEMA_PATCHES = [
  'ALTER TABLE profiles ADD COLUMN status VARCHAR(50) DEFAULT \'active\'',
  'ALTER TABLE shareholders MODIFY COLUMN email VARCHAR(255) NULL',
  'ALTER TABLE shareholders MODIFY COLUMN name VARCHAR(255) NULL',
  'ALTER TABLE shareholders ADD COLUMN full_name VARCHAR(255) NULL',
  'ALTER TABLE shareholders ADD COLUMN phone_number VARCHAR(50) NULL',
  'ALTER TABLE shareholders MODIFY COLUMN phone VARCHAR(50) NULL',
  'ALTER TABLE shareholders ADD COLUMN country_code VARCHAR(10) NULL',
  'ALTER TABLE shareholders ADD COLUMN full_phone_number VARCHAR(50) NULL',
  'ALTER TABLE shareholders ADD COLUMN company_name VARCHAR(255) NULL',
  'ALTER TABLE shareholders ADD COLUMN address TEXT NULL',
  'ALTER TABLE shareholders ADD COLUMN nationality VARCHAR(100) NULL',
  'ALTER TABLE shareholders ADD COLUMN investment_amount DECIMAL(14,2) NULL',
  'ALTER TABLE shareholders ADD COLUMN signature LONGTEXT NULL',
  'ALTER TABLE shareholders ADD COLUMN signature_image_url TEXT NULL',
  'ALTER TABLE shareholders ADD COLUMN agreement_signed_at DATETIME NULL',
  'ALTER TABLE shareholders ADD COLUMN is_guest TINYINT(1) DEFAULT 1',
  'ALTER TABLE shareholders ADD COLUMN user_id CHAR(36) NULL',
  'ALTER TABLE shareholders ADD COLUMN submitted_at DATETIME NULL',
  'ALTER TABLE shareholders ADD COLUMN approved_at DATETIME NULL',
  'ALTER TABLE shareholders ADD COLUMN approved_by CHAR(36) NULL',
  'ALTER TABLE shareholders ADD COLUMN rejection_reason TEXT NULL',
  'ALTER TABLE shareholders ADD COLUMN admin_notes TEXT NULL',
  'ALTER TABLE whatsapp_message_logs ADD COLUMN recipient_phone VARCHAR(50) NULL',
  'ALTER TABLE whatsapp_message_logs ADD COLUMN related_registration_id CHAR(36) NULL',
  'ALTER TABLE whatsapp_message_logs ADD COLUMN retry_count INT DEFAULT 0',
  'ALTER TABLE whatsapp_message_logs ADD COLUMN sent_at DATETIME NULL',
];

export async function applySchemaPatches(pool) {
  for (const sql of SCHEMA_PATCHES) {
    try {
      await pool.query(sql);
      const col = sql.match(/ADD COLUMN `?(\w+)`?|MODIFY COLUMN `?(\w+)`?/i);
      console.log('Patched:', col?.[1] || col?.[2] || sql.slice(0, 40));
    } catch (err) {
      if (err.code === 'ER_DUP_FIELDNAME') continue;
      if (err.code === 'ER_BAD_FIELD_ERROR' && sql.includes('MODIFY')) continue;
      console.warn('Patch skipped:', err.message);
    }
  }
}
