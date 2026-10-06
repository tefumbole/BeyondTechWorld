/** Live public header links. Hidden items are omitted from the site and from Help. */
export const PUBLIC_MENU = [
  { id: 'home', label: 'Home', path: '/', hidden: false },
  { id: 'training', label: 'Training', path: '/trainings', hidden: false },
  { id: 'events', label: 'Events', path: '/events', hidden: false },
  { id: 'register', label: 'Register Now', path: '/register-now', hidden: false },
  { id: 'apply', label: 'Apply Now', path: '/apply-now', hidden: false, special: true },
  { id: 'about', label: 'About Us', path: '/about', hidden: false },
  { id: 'shareholders', label: 'Shareholders', path: '/shareholders', hidden: false },
  { id: 'contact', label: 'Contact Us', path: '/contact', hidden: false },
  { id: 'scan', label: 'Scan QR', path: '/qr-scanner', hidden: false },
];

export function visiblePublicMenu(items = PUBLIC_MENU) {
  return (items || []).filter((item) => item && item.hidden !== true && item.label && item.path);
}
