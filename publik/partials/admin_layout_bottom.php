<?php
// admin_layout_bottom.php
?>
      </div>
    </div>
  </main>

  <script>
  (function(){
    const sidebar   = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const closeBtn  = document.getElementById('sidebarClose');
    const overlay   = document.getElementById('sidebarOverlay');

    if (!sidebar || !toggleBtn || !overlay) return;

    function openSidebar(){ document.body.classList.add('sidebar-open'); }
    function closeSidebar(){ document.body.classList.remove('sidebar-open'); }
    function toggleSidebar(){ document.body.classList.toggle('sidebar-open'); }

    toggleBtn.addEventListener('click', toggleSidebar);
    overlay.addEventListener('click', closeSidebar);
    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);

    // Tutup saat klik menu (link) di sidebar
    sidebar.addEventListener('click', function(e){
      const a = e.target.closest('a');
      if (!a) return;

      const href = a.getAttribute('href') || '';
      // kalau dropdown kamu pakai href="#", jangan ditutup
      if (href && href !== '#') closeSidebar();
    });

    // ESC menutup
    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape') closeSidebar();
    });
  })();
  </script>

</body>
</html>
