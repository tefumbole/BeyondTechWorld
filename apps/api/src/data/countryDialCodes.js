/** Cameroon first, Rwanda second, then the rest by country name. */
const REST = [
  ['Afghanistan', '93'],
  ['Albania', '355'],
  ['Algeria', '213'],
  ['Andorra', '376'],
  ['Angola', '244'],
  ['Argentina', '54'],
  ['Armenia', '374'],
  ['Australia', '61'],
  ['Austria', '43'],
  ['Azerbaijan', '994'],
  ['Bahrain', '973'],
  ['Bangladesh', '880'],
  ['Belgium', '32'],
  ['Benin', '229'],
  ['Botswana', '267'],
  ['Brazil', '55'],
  ['Burkina Faso', '226'],
  ['Burundi', '257'],
  ['Canada', '1'],
  ['Chad', '235'],
  ['China', '86'],
  ['Congo', '242'],
  ['DR Congo', '243'],
  ['Côte d’Ivoire', '225'],
  ['Denmark', '45'],
  ['Egypt', '20'],
  ['Equatorial Guinea', '240'],
  ['Ethiopia', '251'],
  ['Finland', '358'],
  ['France', '33'],
  ['Gabon', '241'],
  ['Gambia', '220'],
  ['Germany', '49'],
  ['Ghana', '233'],
  ['Greece', '30'],
  ['Guinea', '224'],
  ['Hong Kong', '852'],
  ['India', '91'],
  ['Indonesia', '62'],
  ['Ireland', '353'],
  ['Israel', '972'],
  ['Italy', '39'],
  ['Japan', '81'],
  ['Kenya', '254'],
  ['Lebanon', '961'],
  ['Liberia', '231'],
  ['Libya', '218'],
  ['Luxembourg', '352'],
  ['Madagascar', '261'],
  ['Malawi', '265'],
  ['Malaysia', '60'],
  ['Mali', '223'],
  ['Mexico', '52'],
  ['Morocco', '212'],
  ['Mozambique', '258'],
  ['Namibia', '264'],
  ['Netherlands', '31'],
  ['New Zealand', '64'],
  ['Niger', '227'],
  ['Nigeria', '234'],
  ['Norway', '47'],
  ['Pakistan', '92'],
  ['Philippines', '63'],
  ['Poland', '48'],
  ['Portugal', '351'],
  ['Qatar', '974'],
  ['Saudi Arabia', '966'],
  ['Senegal', '221'],
  ['Sierra Leone', '232'],
  ['Singapore', '65'],
  ['South Africa', '27'],
  ['South Sudan', '211'],
  ['Spain', '34'],
  ['Sweden', '46'],
  ['Switzerland', '41'],
  ['Tanzania', '255'],
  ['Togo', '228'],
  ['Tunisia', '216'],
  ['Turkey', '90'],
  ['Uganda', '256'],
  ['Ukraine', '380'],
  ['United Arab Emirates', '971'],
  ['United Kingdom', '44'],
  ['United States', '1'],
  ['Zambia', '260'],
  ['Zimbabwe', '263'],
];

export const COUNTRY_DIAL_CODES = [
  { name: 'Cameroon', dial: '237' },
  { name: 'Rwanda', dial: '250' },
  ...REST
    .map(([name, dial]) => ({ name, dial }))
    .sort((a, b) => a.name.localeCompare(b.name)),
];

export function findCountry(dial) {
  return COUNTRY_DIAL_CODES.find((row) => row.dial === String(dial || '').replace(/\D/g, ''));
}

export function combinePhone(dial, local) {
  const code = String(dial || '').replace(/\D/g, '');
  let digits = String(local || '').replace(/\D/g, '');
  if (!code || !digits) return '';
  if (digits.startsWith('0')) digits = digits.slice(1);
  if (digits.startsWith(code)) digits = digits.slice(code.length);
  const full = `+${code}${digits}`;
  return /^\+\d{8,15}$/.test(full) ? full : '';
}
