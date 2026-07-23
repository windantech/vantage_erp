<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Email Template Builder</title>
  <!-- GrapesJS core -->
  <link href="https://unpkg.com/grapesjs/dist/css/grapes.min.css" rel="stylesheet"/>
  <script src="https://unpkg.com/grapesjs"></script>
  <!-- GrapesJS Newsletter preset (for email components) -->
  <script src="https://unpkg.com/grapesjs-preset-newsletter"></script>
  <style>
    body, html { height:100%; margin:0; }
    #gjs { height:100vh; }
    .top-bar { padding:8px; background:#333; color:#fff; display:flex; align-items:center; }
    .top-bar input { margin-right:8px; }
  </style>
</head>
<body>

  <div class="top-bar">
    <input id="tmpl-name" type="text" placeholder="Template name…" />
    <button id="save-btn">Save Template</button>
  </div>

  <div id="gjs"></div>

  <script>
    // 1) Initialize GrapesJS with the newsletter preset
    const editor = grapesjs.init({
      container: '#gjs',
      height: '100%',
      fromElement: false,
      storageManager: { type: null }, // disable built-in storage
      plugins: ['gjs-preset-newsletter'],
      pluginsOpts: {
        'gjs-preset-newsletter': {}
      }
    });

    // 2) Handle Save: serialize JSON and POST to server
    document.getElementById('save-btn').onclick = () => {
      const name = document.getElementById('tmpl-name').value.trim();
      if (!name) return alert('Give your template a name.');
      const payload = editor.getComponents();
      const css     = editor.getCss();
      const data    = { name, payload, css };

      fetch('save_template.php', {
        method: 'POST',
        headers: { 'Content-Type':'application/json' },
        body: JSON.stringify(data)
      })
      .then(r => r.json())
      .then(res => {
        if (res.success) alert('Template saved! ID: '+res.id);
        else alert('Save failed: '+res.error);
      });
    };
  </script>

</body>
</html>
