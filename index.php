<?php
/**
 * mp3-dana - Minimalist Single-File Music Player
 * @license MIT
 * @author yusun000
 */

require_once('getid3/getid3.php');
$getID3 = new getID3;

// =========================================================================
//  ↓↓↓ CONFIGURATION / 基本設定 ↓↓↓
// =========================================================================
$allowedRoots = [
    'default' => 'music',      // Default music folder / デフォルトの音楽フォルダ
    'usb'     => 'music_usb'   // Additional folder (Optional) / 追加フォルダ（任意）
];
// =========================================================================

$rootKey = $_GET['root'] ?? 'default';
if (!array_key_exists($rootKey, $allowedRoots)) $rootKey = 'default';
$musicRootName = $allowedRoots[$rootKey];
$baseDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . $musicRootName);

if (!$baseDir) die("Error: Music directory not found.");

$requestedPathRel = $_GET['path'] ?? '';
$requestedPathRel = str_replace(['..', '\\'], '', $requestedPathRel);
$fullPath = realpath($baseDir . DIRECTORY_SEPARATOR . $requestedPathRel);

if ($fullPath === false || strpos($fullPath, $baseDir) !== 0) {
    $fullPath = $baseDir; $requestedPathRel = '';
}

$hasQR = file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'qrcode.min.js');

// --- API: Lyrics & Info ---
if (isset($_GET['info'])) {
    $infoRelPath = $_GET['info'] ?? '';
    $cleanPath = preg_replace('/^' . preg_quote($musicRootName . '/', '/') . '/', '', $infoRelPath);
    $infoAbsPath = realpath($baseDir . DIRECTORY_SEPARATOR . $cleanPath);
    $lyrics = "";
    if ($infoAbsPath && is_file($infoAbsPath)) {
        $fileInfo = $getID3->analyze($infoAbsPath);
        $lyrics = $fileInfo['comments']['lyrics'][0] ?? $fileInfo['id3v2']['comments']['unsynchronised_lyric'][0] ?? "";
    }
    header('Content-Type: application/json');
    echo json_encode(['lyrics' => $lyrics]);
    exit;
}

$noCoverSvg = 'data:image/svg+xml;base64,'.base64_encode('<svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><circle cx="100" cy="100" r="95" fill="#111" stroke="#333" stroke-width="2"/><circle cx="100" cy="100" r="15" fill="#222"/><path d="M95 110 L95 75 Q95 65 115 60 L115 90" stroke="#ff9900" stroke-width="5" fill="none" stroke-linecap="round"/><circle cx="85" cy="115" r="12" fill="#ff9900"/></svg>');

function getAlbumCover($dirAbs, $dirRel, $musicRootName, $defaultSvg) {
    global $getID3;
    $cacheName = 'cover_cache.jpg';
    $cacheAbs = $dirAbs . DIRECTORY_SEPARATOR . $cacheName;
    $cacheUrl = $musicRootName . ($dirRel ? '/' . $dirRel : '') . '/' . $cacheName;
    if (file_exists($cacheAbs)) return $cacheUrl;
    $files = glob($dirAbs . "/*.{mp3,m4a,w4a}", GLOB_BRACE);
    if (!empty($files)) {
        foreach($files as $f) {
            $info = $getID3->analyze($f);
            if (isset($info['comments']['picture'][0])) {
                if (is_writable($dirAbs)) {
                    file_put_contents($cacheAbs, $info['comments']['picture'][0]['data']);
                    return $cacheUrl;
                }
            }
        }
    }
    $subDirs = glob($dirAbs . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
    foreach ($subDirs as $sub) {
        $subFiles = glob($sub . "/*.{mp3,m4a,w4a}", GLOB_BRACE);
        if (!empty($subFiles)) {
            $info = $getID3->analyze($subFiles[0]);
            if (isset($info['comments']['picture'][0])) {
                if (is_writable($dirAbs)) {
                    file_put_contents($cacheAbs, $info['comments']['picture'][0]['data']);
                    return $cacheUrl;
                }
            }
        }
    }
    return $defaultSvg;
}

function renderBreadcrumbs($path, $rootKey) {
    $parts = array_filter(explode('/', $path));
    $html = '<a href="?root='.urlencode($rootKey).'&path=" style="color:var(--accent); text-decoration:none;">🏠</a>';
    $acc = '';
    foreach ($parts as $part) {
        $acc .= ($acc ? '/' : '') . $part;
        $html .= '<span style="opacity:0.3; margin: 0 8px;">/</span>';
        $html .= '<a href="?root='.urlencode($rootKey).'&path='.urlencode($acc).'" style="color:inherit; text-decoration:none;">'.htmlspecialchars($part).'</a>';
    }
    return $html;
}

$items = scandir($fullPath);
$directories = []; $songs = [];
if ($items !== false) {
    foreach ($items as $item) {
        if ($item[0] === '.') continue;
        $abs = $fullPath . DIRECTORY_SEPARATOR . $item;
        $rel = trim($requestedPathRel . '/' . $item, '/');
        if (is_dir($abs)) {
            $directories[] = ['name' => $item, 'path' => $rel, 'cover' => getAlbumCover($abs, $rel, $musicRootName, $noCoverSvg)];
        } elseif (in_array(strtolower(pathinfo($item, PATHINFO_EXTENSION)), ['mp3','m4a','w4a'])) {
            $songs[] = ['name' => $item, 'path' => $rel];
        }
    }
}
sort($songs);
$currentCover = getAlbumCover($fullPath, $requestedPathRel, $musicRootName, $noCoverSvg);
$hasSongs = (count($songs) > 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($requestedPathRel ?: 'Home'); ?> // mp3-dana</title>
    <?php if ($hasQR): ?><script src="qrcode.min.js"></script><?php endif; ?>
    <style>
        :root { --bg: #0d0d0d; --card: #181818; --accent: #ff9900; --text: #eee; --panel: #1a1a1a; --border: #333; }
        body.light { --bg: #f5f5f5; --card: #ffffff; --accent: #e67e22; --text: #333; --panel: #eaeaea; --border: #ccc; }
        body { font-family: sans-serif; background: var(--bg); color: var(--text); margin: 0; padding-bottom: 160px; transition: 0.3s; }
        .container { max-width: 1100px; margin: 0 auto; padding: 20px; }
        .root-nav { display: flex; gap: 10px; margin-bottom: 20px; }
        .root-nav a { text-decoration: none; color: #888; background: var(--panel); padding: 8px 18px; border-radius: 20px; font-size: 0.8rem; border: 1px solid var(--border); }
        .root-nav a.active { background: var(--accent); color: #fff; border-color: var(--accent); font-weight: bold; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; background: var(--panel); padding: 12px 20px; border-radius: 8px; border: 1px solid var(--border); }
        .theme-btn, .view-btn { background: #333; color: #fff; border: none; padding: 0 12px; height: 32px; line-height: 32px; border-radius: 4px; cursor: pointer; font-size: 0.75rem; margin-left: 5px; vertical-align: middle; display: inline-flex; align-items: center; gap: 5px; }
        .view-btn.active { background: var(--accent); }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 25px; }
        .card { background: var(--card); padding: 15px; border-radius: 10px; text-decoration: none; color: inherit; transition: 0.2s; border: 1px solid var(--border); }
        .card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        .card img { width: 100%; aspect-ratio: 1/1; object-fit: cover; margin-bottom: 12px; border-radius: 4px; }
        .view-circle .card img { border-radius: 50%; }
        .view-list .grid { grid-template-columns: 1fr; }
        .view-list .card { display: flex; align-items: center; gap: 20px; padding: 10px; }
        .view-list .card img { width: 60px; margin-bottom: 0; }
        .song-item { padding: 12px 20px; border-bottom: 1px solid rgba(128,128,128,0.2); cursor: pointer; display: flex; align-items: center; }
        .song-item:hover { background: rgba(128,128,128,0.1); }
        .song-item.playing { color: var(--accent); font-weight: bold; border-left: 4px solid var(--accent); }
        #player-bar { position: fixed; bottom: 0; width: 100%; background: var(--panel); border-top: 1px solid var(--border); backdrop-filter: blur(15px); padding: 20px 0; z-index: 1000; display: <?php echo $hasSongs ? 'block' : 'none'; ?>; }
        .player-inner { max-width: 1200px; margin: 0 auto; display: flex; align-items: center; gap: 25px; padding: 0 20px; }
        #cover-art-mini { width: 64px; height: 64px; border-radius: 4px; object-fit: cover; border: 1px solid var(--border); transition: 0.3s; cursor: pointer; flex-shrink: 0; }
        #cover-art-mini.large { position: fixed; bottom: 130px; left: 20px; width: 300px; height: 300px; z-index: 1100; box-shadow: 0 0 30px rgba(0,0,0,0.5); }
        .player-info { flex: 1; min-width: 0; }
        #play-title { font-size: 0.9rem; font-weight: bold; margin-bottom: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        audio { width: 100%; height: 35px; filter: invert(0.9) hue-rotate(180deg); }
        .btn-group { display: flex; gap: 10px; margin-top: 10px; align-items: center; }
        .ctrl-btn { background: #333; border: 1px solid #444; color: #fff; padding: 4px 14px; border-radius: 15px; cursor: pointer; font-size: 0.7rem; }
        .ctrl-btn.active { background: var(--accent); }
        #qr-box { display: none; position: absolute; bottom: 100px; left: 20px; background: white; padding: 12px; border-radius: 8px;  box-shadow: 0 0 20px rgba(0,0,0,0.5); z-index: 1200; }
        #qr-box img { border-radius: 0 !important; }
        #lyrics-box { display: none; position: fixed; bottom: 140px; right: 20px; width: 320px; max-height: 50vh; background: rgba(0,0,0,0.9); padding: 25px; border-radius: 12px; border: 1px solid #444; font-size: 0.85rem; overflow-y: auto; white-space: pre-wrap; line-height: 1.8; color: #fff; z-index: 1100; pointer-events: none; }
    </style>
</head>
<body class="view-square">

<div class="container">
    <div class="root-nav">
        <?php foreach ($allowedRoots as $key => $name): ?>
            <a href="?root=<?php echo urlencode($key); ?>" class="<?php echo $rootKey === $key ? 'active' : ''; ?>"><?php echo htmlspecialchars(strtoupper($key)); ?></a>
        <?php endforeach; ?>
    </div>

    <div class="toolbar">
        <div style="font-size: 0.9rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo renderBreadcrumbs($requestedPathRel, $rootKey); ?></div>
        <div style="flex-shrink: 0; display: flex;">
            <button class="theme-btn" onclick="toggleTheme()"><span>🌓</span> Theme</button>
            <button class="view-btn" id="btn-circle" onclick="setView('view-circle')">Circle</button>
            <button class="view-btn" id="btn-square" onclick="setView('view-square')">Square</button>
            <button class="view-btn" id="btn-list" onclick="setView('view-list')">List</button>
        </div>
    </div>

    <div class="grid">
        <?php foreach ($directories as $dir): ?>
            <a class="card" href="?root=<?php echo urlencode($rootKey); ?>&path=<?php echo urlencode($dir['path']); ?>">
                <img src="<?php echo htmlspecialchars($dir['cover']); ?>" loading="lazy">
                <div><?php echo htmlspecialchars($dir['name']); ?></div>
            </a>
        <?php endforeach; ?>
    </div>

    <div style="margin-top: 40px;">
        <?php foreach ($songs as $index => $song): ?>
            <div class="song-item song" data-src="<?php echo htmlspecialchars($musicRootName . '/' . $song['path']); ?>" onclick="playSong(this, <?php echo $index; ?>)">
                <span style="width: 35px; opacity: 0.3; font-size: 0.8rem;"><?php echo str_pad($index+1, 2, '0', STR_PAD_LEFT); ?></span>
                <span class="s-name"><?php echo htmlspecialchars($song['name']); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="lyrics-box"></div>

<div id="player-bar">
    <?php if ($hasQR): ?><div id="qr-box"><div id="qrcode"></div></div><?php endif; ?>
    <div class="player-inner">
        <img id="cover-art-mini" src="<?php echo htmlspecialchars($currentCover); ?>" onclick="toggleCoverZoom()" title="Click to zoom">
        <div class="player-info">
            <div id="play-title">Ready to play</div>
            <audio id="audio-main" controls autoplay></audio>
            <div class="btn-group">
                <button id="shuf-btn" class="ctrl-btn" onclick="toggleShuffle()">SHUFFLE</button>
                <button id="rep-btn" class="ctrl-btn active" onclick="toggleRepeat()">ALL REPEAT</button>
                <?php if ($hasQR): ?><button class="ctrl-btn" onclick="toggleQR()">QR</button><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    const audio = document.getElementById('audio-main'), 
          songElems = document.querySelectorAll('.song'), 
          lyricsBox = document.getElementById('lyrics-box'), 
          miniCover = document.getElementById('cover-art-mini');
    
    let isShuffle = false, repeatMode = 1, currentIndex = -1, qrcode = null;

    function setView(mode) {
        document.body.classList.remove('view-circle','view-square','view-list');
        document.body.classList.add(mode);
        localStorage.setItem('music_view_mode', mode);
        document.querySelectorAll('.view-btn').forEach(b => b.classList.toggle('active', b.getAttribute('onclick').includes(mode)));
    }
    
    function toggleTheme() {
        const isLight = document.body.classList.toggle('light');
        localStorage.setItem('music_theme', isLight ? 'light' : 'dark');
    }
    
    function toggleCoverZoom() { miniCover.classList.toggle('large'); }

    if(localStorage.getItem('music_theme') === 'light') document.body.classList.add('light');
    setView(localStorage.getItem('music_view_mode') || 'view-square');

    function toggleShuffle() { isShuffle = !isShuffle; document.getElementById('shuf-btn').classList.toggle('active', isShuffle); }
    function toggleRepeat() {
        repeatMode = (repeatMode + 1) % 3;
        document.getElementById('rep-btn').innerText = ["OFF", "ALL REPEAT", "ONE REPEAT"][repeatMode];
        document.getElementById('rep-btn').classList.toggle('active', repeatMode !== 0);
    }

    <?php if ($hasQR): ?>
    function toggleQR() {
        const box = document.getElementById('qr-box');
        if (!qrcode) qrcode = new QRCode(document.getElementById("qrcode"), { text: window.location.href, width: 160, height: 160 });
        if (box.style.display === 'none' || box.style.display === '') {
            qrcode.makeCode(window.location.href);
            box.style.display = 'block';
        } else { box.style.display = 'none'; }
    }
    <?php endif; ?>

    async function playSong(el, idx) {
        currentIndex = idx;
        songElems.forEach(s => s.classList.remove('playing')); el.classList.add('playing');
        const songName = el.querySelector('.s-name').innerText;
        const songSrc = el.getAttribute('data-src');
        document.getElementById('play-title').innerText = songName;
        document.title = `${songName} // mp3-dana`;
        audio.src = songSrc; audio.play();

        try {
            const res = await fetch(`?root=<?php echo urlencode($rootKey); ?>&info=${encodeURIComponent(songSrc)}`);
            const data = await res.json();
            if (data.lyrics && data.lyrics.trim().length > 0) { 
                lyricsBox.innerText = data.lyrics; lyricsBox.style.display = 'block'; 
            } else { lyricsBox.style.display = 'none'; }
        } catch(e) { lyricsBox.style.display = 'none'; }
    }

    audio.onended = () => {
        if (repeatMode === 2) { playSong(songElems[currentIndex], currentIndex); return; }
        if (isShuffle) { playSong(songElems[Math.floor(Math.random() * songElems.length)], 0); return; }
        let next = currentIndex + 1;
        if (next < songElems.length) playSong(songElems[next], next);
        else if (repeatMode === 1) playSong(songElems[0], 0);
    };
</script>
</body>
</html>


