<?php
// ceo_dashboard/bde_dashboard.php
// Wrapper that renders the private BDE dashboard INSIDE the CRM chrome, so the
// main left nav (and top nav) are present. The prototype itself lives in
// bde_dashboard_view.php and is embedded in an isolated iframe — that keeps the
// prototype's own design system from colliding with the CRM's Bootstrap styles
// (both define .badge, .table, etc.). Reached by direct URL only for now.
session_start();
require_once 'header.php';   // main CRM sidebar + chrome + $conn
?>
<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>
        <div class="container-fluid px-0 py-2">
            <iframe id="bdeFrame"
                    src="bde_dashboard_view.php"
                    title="BDE Command Centre"
                    scrolling="no"
                    style="width:100%;border:0;display:block;min-height:700px"></iframe>
        </div>
    </div>
</section>

<script>
// Auto-size the embedded dashboard to its content so there is no inner
// scrollbar; the page scrolls as one. Falls back to min-height if no message.
(function () {
    var frame = document.getElementById('bdeFrame');
    if (!frame) return;
    window.addEventListener('message', function (e) {
        if (e.data && typeof e.data.vaslHeight === 'number' && e.data.vaslHeight > 0) {
            frame.style.height = (e.data.vaslHeight + 24) + 'px';
        }
    });
}());
</script>

<?php require_once 'footer.php'; ?>
