/**
 * Ícones em SVG puro (linha, 24x24, currentColor).
 * Escolhidos para leitura instantânea: cada módulo do sistema tem
 * um ícone e uma cor fixos em toda a interface.
 */
const ICONS = {
  logo: `<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 5.5C4 4.67 4.67 4 5.5 4H12V20H5.5C4.67 20 4 19.33 4 18.5V5.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M20 5.5C20 4.67 19.33 4 18.5 4H12V20H18.5C19.33 20 20 19.33 20 18.5V5.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9 8H8.2M9 11H8.2M16 8H15.2M16 11H15.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>`,

  dashboard: `<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3.5" y="3.5" width="7.5" height="9" rx="2" stroke="currentColor" stroke-width="1.7"/><rect x="13" y="3.5" width="7.5" height="5.5" rx="2" stroke="currentColor" stroke-width="1.7"/><rect x="13" y="11.5" width="7.5" height="9" rx="2" stroke="currentColor" stroke-width="1.7"/><rect x="3.5" y="15" width="7.5" height="5.5" rx="2" stroke="currentColor" stroke-width="1.7"/></svg>`,

  students: `<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.7"/><path d="M3.5 20c0-3.31 2.46-6 5.5-6s5.5 2.69 5.5 6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><circle cx="17" cy="7.5" r="2.3" stroke="currentColor" stroke-width="1.6"/><path d="M15 20c-.1-2.6 1.2-4.6 3-5.3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>`,

  teachers: `<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="4" y="4" width="16" height="11" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="M8 19h8M12 15v4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M7.5 10.5l2.7 2.2 6.3-5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>`,

  schedule: `<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3.5" y="5" width="17" height="15" rx="2.2" stroke="currentColor" stroke-width="1.7"/><path d="M3.5 9.5h17M8 3v3.5M16 3v3.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M7.5 13h3M7.5 16.3h5M13.5 13h3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>`,

  logout: `<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 4H6.5A2.5 2.5 0 0 0 4 6.5v11A2.5 2.5 0 0 0 6.5 20H9" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M13 8l4 4-4 4M17 12H9" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>`,

  plus: `<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>`,

  edit: `<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15.7 4.8l3.5 3.5L8 19.5H4.5V16L15.7 4.8Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>`,

  trash: `<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 7h14M9.5 7V5.2c0-.66.54-1.2 1.2-1.2h2.6c.66 0 1.2.54 1.2 1.2V7M7 7l.7 11.3c.05.9.8 1.7 1.8 1.7h5c1 0 1.75-.8 1.8-1.7L17 7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>`,

  search: `<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="10.5" cy="10.5" r="6" stroke="currentColor" stroke-width="1.7"/><path d="M19 19l-4-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>`,

  close: `<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>`,

  mail: `<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3.5" y="5.5" width="17" height="13" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M4.5 7l7.5 5.5L19.5 7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>`,

  lock: `<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="5" y="10.5" width="14" height="9.5" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>`,

  eye: `<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><circle cx="12" cy="12" r="2.6" stroke="currentColor" stroke-width="1.6"/></svg>`,

  eyeOff: `<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3.5 3.5l17 17" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M10.6 6.1C15.7 5.3 20 12 20 12s-1.3 2.1-3.5 3.7M6.5 8.3C4.4 9.7 3 12 3 12s3.5 6.5 9.5 6.5c1 0 1.9-.15 2.8-.42" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M9.9 10.2a2.6 2.6 0 0 0 3.6 3.6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>`,

  chevronDown: `<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>`,

  globe: `<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.6"/><path d="M3.5 12h17M12 3.5c2.4 2.3 3.7 5.2 3.7 8.5s-1.3 6.2-3.7 8.5c-2.4-2.3-3.7-5.2-3.7-8.5S9.6 5.8 12 3.5Z" stroke="currentColor" stroke-width="1.6"/></svg>`,

  alert: `<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4.5 21 19.5H3L12 4.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M12 10v4M12 16.3v.1" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>`
};

function icon(name, extraClass) {
  return (ICONS[name] || '').replace('<svg ', `<svg class="icon${extraClass ? ' ' + extraClass : ''}" `);
}
