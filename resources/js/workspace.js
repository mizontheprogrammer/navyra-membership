const toggle = document.getElementById('theme-toggle');
function syncTheme() {
 const dark = document.documentElement.dataset.theme === 'dark';
 if (toggle) { toggle.textContent = dark ? 'Light mode' : 'Dark mode'; toggle.setAttribute('aria-pressed', String(dark)); }
}
toggle?.addEventListener('click', () => {
 document.documentElement.dataset.theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
 try { localStorage.setItem('navyra-theme', document.documentElement.dataset.theme); } catch {}
 syncTheme();
});
syncTheme();
const category = document.getElementById('membership_category_id');
const company = document.getElementById('company_name');
function updateCompany() { if (category && company) company.required = category.selectedOptions[0]?.textContent.trim().startsWith('Corporate') || false; }
category?.addEventListener('change', updateCompany);
updateCompany();
