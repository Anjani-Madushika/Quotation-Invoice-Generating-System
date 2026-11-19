<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? 'Home') . ' | Quotation System') ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
  // COLOUR THEME: change these 8 values to re-colour the whole project
  tailwind.config = { theme: { extend: {
    colors: { brand: {
      50:'#f1f5fb', 100:'#e2ebf6', 200:'#c3d4ea', 400:'#5b86c7',
      500:'#2f5fb0', 600:'#1e4488', 700:'#163468', 900:'#0b1f44'
    }, gold: '#e0a526' },
    fontFamily: { sans: ['"Plus Jakarta Sans"','system-ui','sans-serif'], inter: ['Inter','system-ui','sans-serif'] }
  }}}
</script>
<link rel="icon" type="image/svg+xml" href="<?= BASE ?>assets/img/logo.svg">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE ?>assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: 1 ?>">
