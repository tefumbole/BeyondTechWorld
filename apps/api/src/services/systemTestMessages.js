import { COMPANY_NAME } from '../constants/branding.js';

const DIVIDER = '━━━━━━━━━━━━━━━';

function footer() {
  return `\n_${COMPANY_NAME}_`;
}

function heading(emoji, title) {
  return `${emoji} *${title}*\n${DIVIDER}\n\n`;
}

function greeting(name) {
  return `Hello *${name}*,\n\n`;
}

function bullet(label, value) {
  return `▪️ *${label}:* ${value}\n`;
}

function pack(emoji, title, intro, lines) {
  const bodies = [];
  let current = intro.replace(/\s+$/, '');
  for (const line of lines) {
    const candidate = `${current}\n${line}`;
    if (candidate.length > 2800 && current !== intro.replace(/\s+$/, '')) {
      bodies.push(current);
      current = line;
    } else {
      current = candidate;
    }
  }
  bodies.push(current);
  return bodies.map((body, index) => {
    const label = bodies.length > 1 ? `${title} (${index + 1}/${bodies.length})` : title;
    return `${heading(emoji, label)}${body}${footer()}`;
  });
}

function resultLines(rows) {
  return rows.map((row) => {
    let line = `• *${row.section}:* ${row.text}`;
    if (row.note) line += `\n  _${row.note}_`;
    return line;
  });
}

export function otpMessage(code) {
  return `${heading('🔐', 'System test code')}${greeting('there')}Use this code to open your system test.\n${bullet('Code', code)}${bullet('Expires', '10 minutes')}\nDo not share this code.\n${footer()}`;
}

export function loginMessage(name, username, password, loginUrl) {
  return `${heading('🔐', 'Test login')}${greeting(name)}Your test account is ready. Use it to sign in while you test the website.\n${bullet('Username', username)}${bullet('Password', password)}\nOpen this link to sign in:\n${loginUrl}\n\nSettings is not included on this account.\n${footer()}`;
}

export function testerResultMessages(report) {
  const fails = report.rows.filter((row) => row.result === 'fails');
  let intro = greeting(report.tester_name);
  intro += `Your ${COMPANY_NAME} system test has been received. The administrator also received this result and a list of what does not work.\n`;
  intro += bullet('Works', report.counts.works);
  intro += bullet('Does not work', report.counts.fails);
  intro += bullet('Not tested', report.counts.skipped);
  const lines = resultLines(fails);
  if (report.summary) lines.push(`*Note:* ${report.summary.slice(0, 500)}`);
  return pack('✅', 'System test result', intro, lines);
}

export function adminFailureMessages(report) {
  const fails = report.rows.filter((row) => row.result === 'fails');
  let intro = greeting('Administrator');
  intro += `*${report.tester_name}* (${report.tester_phone}) submitted a system test.\n`;
  intro += fails.length
    ? `Focus on these *${fails.length}* items that do not work:\n`
    : 'Nothing was marked as not working.\n';
  return pack('⚠️', 'What does not work', intro, resultLines(fails));
}

export function adminFullMessages(report, reportUrl) {
  let intro = greeting('Administrator');
  intro += `Full result from *${report.tester_name}* (${report.tester_phone}).\n`;
  intro += bullet('Works', report.counts.works);
  intro += bullet('Does not work', report.counts.fails);
  intro += bullet('Not tested', report.counts.skipped);
  intro += '\nThe items that do not work are in the previous message.\n';
  const lines = [];
  const works = report.rows.filter((row) => row.result === 'works');
  const skipped = report.rows.filter((row) => row.result === 'skipped');
  if (works.length) lines.push('*Works*', ...resultLines(works));
  if (skipped.length) lines.push('*Not tested*', ...resultLines(skipped));
  if (report.summary) lines.push(`*Note:* ${report.summary.slice(0, 500)}`);
  lines.push(`Open the full result:\n${reportUrl}`);
  return pack('📋', 'Tester result', intro, lines);
}

export function emailBody(report, reportUrl) {
  const lines = [
    `${COMPANY_NAME}`,
    'System test result',
    '',
    `Name: ${report.tester_name}`,
    `WhatsApp: ${report.tester_phone}`,
    `Works: ${report.counts.works}`,
    `Does not work: ${report.counts.fails}`,
    `Not tested: ${report.counts.skipped}`,
    '',
  ];
  for (const row of report.rows) {
    const label = row.result === 'works' ? 'Works' : row.result === 'fails' ? 'Does not work' : 'Not tested';
    lines.push(`${row.number}. [${label}] ${row.section}: ${row.text}`);
    if (row.note) lines.push(`   Note: ${row.note}`);
  }
  if (report.summary) {
    lines.push('', `Overall note: ${report.summary}`);
  }
  lines.push('', `Open the saved report: ${reportUrl}`);
  return lines.join('\n');
}
