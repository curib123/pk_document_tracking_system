// Small local SVG set. No icon CDN, external fonts or dynamically evaluated markup.
const paths = {
  file: ['M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z','M14 2v6h6'],
  folder: ['M3 7V5a2 2 0 0 1 2-2h5l2 3h7a2 2 0 0 1 2 2v2','M3 7h17a1 1 0 0 1 1 1.3l-3 10a1 1 0 0 1-1 .7H4a1 1 0 0 1-1-1V7Z'],
  home: ['m3 10 9-7 9 7','M5 9v12h14V9','M9 21v-8h6v8'],
  grid: ['M3 3h7v7H3z','M14 3h7v7h-7z','M3 14h7v7H3z','M14 14h7v7h-7z'],
  user: ['M20 21v-2a7 7 0 0 0-14 0v2','M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8'],
  users: ['M16 21v-2a6 6 0 0 0-12 0v2','M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8','M17 4a4 4 0 0 1 0 7','M22 21v-2a6 6 0 0 0-4-5.65'],
  search: ['M21 21l-5-5','M10.5 17a6.5 6.5 0 1 0 0-13 6.5 6.5 0 0 0 0 13'],
  arrow: ['M4 12h16','m14 6 6 6-6 6'],
  logout: ['M9 5H4v14h5','M9 12h12','m17 8 4 4-4 4'],
  moon: ['M21 12.8A9 9 0 0 1 11.2 3 9 9 0 1 0 21 12.8Z'],
  sun: ['M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8','M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.4 1.4m11.2 11.2L19 19M5 19l1.4-1.4M17.6 6.4 19 5'],
  bell: ['M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9','M10 21h4'],
  clock: ['M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18','M12 7v5l3 2'],
  check: ['m8 12 3 3 8-10','M21 12a9 9 0 1 1-6-8.5'],
  box: ['m3 7 9-4 9 4v10l-9 4-9-4Z','m3 7 9 5 9-5','M12 12v9','m7 5 9 4'],
  storage: ['M3 6c0-5 18-5 18 0s-18 5-18 0Z','M3 6v12c0 5 18 5 18 0V6','M3 12c0 5 18 5 18 0'],
  send: ['m22 2-7 20-4-9-9-4Z','M22 2 11 13'],
  shield: ['m12 3 9 4v5c0 5-9 10-9 10S3 17 3 12V7Z'],
  lock: ['M5 11h14v10H5z','M8 11V7a4 4 0 0 1 8 0v4'],
  pin: ['M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z','M12 7a3 3 0 1 0 0 6 3 3 0 0 0 0-6'],
  sparkle: ['m12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5Z','M20 2v4m-2-2h4'],
  calendar: ['M4 5h16v16H4z','M4 10h16','M8 2v6m8-6v6'],
  refresh: ['M20 7a8 8 0 1 0 1 9','M20 3v5h-5'],
  chevron: ['m6 9 6 6 6-6'],
  menu: ['M4 6h16M4 12h16M4 18h16'],
  eye: ['M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z','M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6']
};
export function icon(name, className = '') {
  const ns = 'http://www.w3.org/2000/svg';
  const node = document.createElementNS(ns, 'svg');
  for (const [key, value] of Object.entries({width:'18',height:'18',viewBox:'0 0 24 24',fill:'none',stroke:'currentColor','stroke-width':'1.7','stroke-linecap':'round','stroke-linejoin':'round','aria-hidden':'true',focusable:'false',class:`ws-icon ${className}`})) node.setAttribute(key,value);
  for (const d of paths[name] || paths.file) {
    const path = document.createElementNS(ns,'path'); path.setAttribute('d',d); node.append(path);
  }
  return node;
}
