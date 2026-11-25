document.querySelectorAll('[data-confirm]').forEach(form => form.addEventListener('submit', event => {if (!confirm(form.dataset.confirm)) event.preventDefault();}));
const filter = document.querySelector('[data-package-filter]');
if(filter)filter.addEventListener('change',()=>{document.querySelectorAll('[data-package-suburb]').forEach(el=>{el.hidden=!!filter.value && !!el.dataset.packageSuburb && el.dataset.packageSuburb!==filter.value;});});
