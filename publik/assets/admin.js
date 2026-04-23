(() => {
  const sidebar = document.getElementById('sidebar');
  const main = document.getElementById('main');
  const btn = document.getElementById('toggleBtn');
  if (!sidebar || !main || !btn) return;

  const saved = localStorage.getItem('multipos_sidebar') || 'open';
  if (saved === 'collapsed') sidebar.classList.add('collapsed');

  const applyMobile = () => {
    const mobile = window.matchMedia('(max-width: 980px)').matches;
    if (mobile) main.classList.toggle('full', sidebar.classList.contains('collapsed'));
    else main.classList.remove('full');
  };

  btn.addEventListener('click', () => {
    sidebar.classList.toggle('collapsed');
    localStorage.setItem(
      'multipos_sidebar',
      sidebar.classList.contains('collapsed') ? 'collapsed' : 'open'
    );
    applyMobile();
  });

  window.addEventListener('resize', applyMobile);
  applyMobile();
})();
