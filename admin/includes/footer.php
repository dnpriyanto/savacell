  </main><!-- /main -->
</div><!-- /main area -->

<script>
// Auto-hide flash messages
setTimeout(() => {
  document.querySelectorAll('[data-flash]').forEach(el => {
    el.style.transition = 'opacity .4s';
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 400);
  });
}, 4000);

// Confirm delete dialogs
document.querySelectorAll('[data-confirm]').forEach(btn => {
  btn.addEventListener('click', function(e) {
    if (!confirm(this.dataset.confirm || 'Yakin ingin menghapus data ini?')) {
      e.preventDefault();
    }
  });
});
</script>
</body>
</html>
