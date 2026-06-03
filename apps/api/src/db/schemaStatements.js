/** Split schema.sql into executable CREATE TABLE statements. */
export function getOrderedCreateStatements(sql) {
  const cleaned = sql.replace(/^--.*$/gm, '').trim();
  return cleaned
    .split(';')
    .map((s) => s.trim())
    .filter((s) => s.length > 0 && /^CREATE TABLE/i.test(s));
}
