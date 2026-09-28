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
    $lrc = "";

    if ($infoAbsPath && is_file($infoAbsPath)) {
        // Prefer a sidecar .lrc file with the same basename as the audio file.
        // Example: "01 - Song.mp3" -> "01 - Song.lrc"
        $lrcBase = pathinfo($infoAbsPath, PATHINFO_DIRNAME)
                 . DIRECTORY_SEPARATOR
                 . pathinfo($infoAbsPath, PATHINFO_FILENAME);

        foreach ([$lrcBase . '.lrc', $lrcBase . '.LRC'] as $lrcCandidate) {
            if (is_file($lrcCandidate) && is_readable($lrcCandidate)) {
                $lrc = file_get_contents($lrcCandidate);
                if ($lrc !== false) break;
                $lrc = "";
            }
        }

        // Existing ID3 lyrics remain available as a fallback.
        $fileInfo = $getID3->analyze($infoAbsPath);
        $lyrics = $fileInfo['comments']['lyrics'][0]
               ?? $fileInfo['id3v2']['comments']['unsynchronised_lyric'][0]
               ?? "";
    }

    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'lyrics' => $lyrics,
        'lrc' => $lrc,
        'synced' => ($lrc !== "")
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
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
        #lyrics-box { display: none; position: fixed; bottom: 140px; right: 20px; width: min(420px, calc(100vw - 40px)); max-height: 58vh; background: rgba(0,0,0,0.92); border-radius: 12px; border: 1px solid #444; color: #fff; z-index: 1100; overflow: hidden; box-shadow: 0 10px 35px rgba(0,0,0,0.35); }
        #lyrics-toolbar { display: flex; align-items: center; gap: 8px; padding: 10px 12px; border-bottom: 1px solid #333; background: rgba(255,255,255,0.04); position: relative; z-index: 4; flex-shrink: 0; }
        #lyrics-status { flex: 1; font-size: 0.72rem; color: #aaa; letter-spacing: 0.04em; }
        .lyrics-btn { background: #333; border: 1px solid #555; color: #fff; padding: 4px 10px; border-radius: 14px; cursor: pointer; font-size: 0.68rem; }
        .lyrics-btn.active { background: var(--accent); border-color: var(--accent); }
        #lyrics-content { max-height: calc(58vh - 46px); overflow-y: auto; padding: 24vh 20px; scroll-behavior: smooth; }
        .lyric-line { margin: 0; padding: 7px 8px; border-radius: 7px; line-height: 1.65; opacity: 0.36; font-size: 0.92rem; transition: opacity 0.22s, transform 0.22s, font-size 0.22s, background 0.22s; cursor: pointer; white-space: pre-wrap; }
        .lyric-line:hover { opacity: 0.8; background: rgba(255,255,255,0.06); }
        .lyric-line.past { opacity: 0.52; }
        .lyric-line.current { opacity: 1; background: rgba(255,153,0,0.14); font-size: 1.08rem; font-weight: bold; transform: scale(1.015); }
        #lyrics-box.focus-mode .lyric-line { display: none; }
        #lyrics-box.focus-mode .lyric-line.current,
        #lyrics-box.focus-mode .lyric-line.current + .lyric-line { display: block; }
        #lyrics-box.focus-mode #lyrics-content { padding-top: 22vh; padding-bottom: 22vh; }
        .plain-lyrics { white-space: pre-wrap; overflow-wrap: anywhere; line-height: 1.8; font-size: 0.88rem; padding: 20px; }
        #lyrics-content.plain-mode { padding: 0; }
        #lyrics-return { display: none; position: absolute; right: 14px; bottom: 14px; z-index: 3; background: var(--accent); color: #fff; border: 0; border-radius: 18px; padding: 7px 12px; cursor: pointer; font-size: 0.7rem; box-shadow: 0 3px 12px rgba(0,0,0,0.35); }
        @media (max-width: 640px) {
            #lyrics-box { left: 10px; right: 10px; bottom: 135px; width: auto; max-height: 55vh; }
            #lyrics-content { max-height: calc(55vh - 46px); }
        }
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

<div id="lyrics-box">
    <div id="lyrics-toolbar">
        <span id="lyrics-status">LYRICS</span>
        <button id="lyrics-follow-btn" class="lyrics-btn active" onclick="resumeLyricsFollow()">FOLLOW</button>
        <button id="lyrics-focus-btn" class="lyrics-btn" onclick="toggleLyricsFocus()">FOCUS</button>
    </div>
    <div id="lyrics-content"></div>
    <button id="lyrics-return" onclick="resumeLyricsFollow()">Return to current line</button>
</div>

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
          lyricsContent = document.getElementById('lyrics-content'),
          lyricsStatus = document.getElementById('lyrics-status'),
          lyricsFollowBtn = document.getElementById('lyrics-follow-btn'),
          lyricsFocusBtn = document.getElementById('lyrics-focus-btn'),
          lyricsReturnBtn = document.getElementById('lyrics-return'),
          miniCover = document.getElementById('cover-art-mini');

    let isShuffle = false, repeatMode = 1, currentIndex = -1, qrcode = null;
    let syncedLyrics = [], currentLyricIndex = -1, lyricsFollow = true, lyricsFocus = false;
    let lyricsProgrammaticScroll = false;

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

    function parseLrc(text) {
        const entries = [];
        let offsetMs = 0;
        const lines = String(text || '').replace(/\r\n?/g, '\n').split('\n');

        for (const rawLine of lines) {
            const offsetMatch = rawLine.match(/^\s*\[offset:([+-]?\d+)\]\s*$/i);
            if (offsetMatch) {
                offsetMs = parseInt(offsetMatch[1], 10) || 0;
                continue;
            }

            const timeTagRe = /\[(\d{1,3}):(\d{2})(?:[.:](\d{1,3}))?\]/g;
            const lyricText = rawLine.replace(timeTagRe, '').trim();
            let match;

            while ((match = timeTagRe.exec(rawLine)) !== null) {
                const minutes = parseInt(match[1], 10);
                const seconds = parseInt(match[2], 10);
                const frac = match[3] || '0';
                const fractionSeconds = parseInt(frac.padEnd(3, '0').slice(0, 3), 10) / 1000;

                entries.push({
                    time: minutes * 60 + seconds + fractionSeconds,
                    text: lyricText || '♪'
                });
            }
        }

        const offsetSeconds = offsetMs / 1000;
        return entries
            .map(item => ({ ...item, time: Math.max(0, item.time + offsetSeconds) }))
            .sort((a, b) => a.time - b.time);
    }

    function resetLyrics() {
        syncedLyrics = [];
        currentLyricIndex = -1;
        lyricsFollow = true;
        lyricsFocus = false;
        lyricsBox.classList.remove('focus-mode');
        lyricsFollowBtn.classList.add('active');
        lyricsFocusBtn.classList.remove('active');
        lyricsReturnBtn.style.display = 'none';
        lyricsContent.innerHTML = '';
        lyricsContent.classList.remove('plain-mode');
        lyricsStatus.textContent = 'LYRICS';
    }

    function renderSyncedLyrics(entries) {
        resetLyrics();
        syncedLyrics = entries;
        lyricsStatus.textContent = 'SYNCED LYRICS';

        const fragment = document.createDocumentFragment();

        entries.forEach((entry, index) => {
            const line = document.createElement('div');
            line.className = 'lyric-line';
            line.dataset.index = index;
            line.dataset.time = entry.time;
            line.textContent = entry.text;

            line.addEventListener('click', () => {
                audio.currentTime = entry.time;
                if (audio.paused) audio.play();
                resumeLyricsFollow();
                updateSyncedLyrics(true);
            });

            fragment.appendChild(line);
        });

        lyricsContent.appendChild(fragment);
        lyricsBox.style.display = 'block';
        updateSyncedLyrics(true);
    }

    function renderPlainLyrics(text) {
        resetLyrics();
        lyricsStatus.textContent = 'ID3 LYRICS';
        lyricsContent.classList.add('plain-mode');

        let normalized = String(text ?? '');

        // Preserve line breaks from ID3/USLT regardless of whether the tag uses
        // CRLF, CR, LF, or contains escaped "\\n" sequences.
        normalized = normalized.replace(/\r\n?/g, '\n');
        if (!normalized.includes('\n') && normalized.includes('\\n')) {
            normalized = normalized.replace(/\\n/g, '\n');
        }

        const plain = document.createElement('div');
        plain.className = 'plain-lyrics';
        plain.textContent = normalized;

        lyricsContent.appendChild(plain);
        lyricsBox.style.display = 'block';
    }

    function findCurrentLyricIndex(time) {
        let lo = 0;
        let hi = syncedLyrics.length - 1;
        let answer = -1;

        while (lo <= hi) {
            const mid = (lo + hi) >> 1;

            if (syncedLyrics[mid].time <= time + 0.05) {
                answer = mid;
                lo = mid + 1;
            } else {
                hi = mid - 1;
            }
        }

        return answer;
    }

    function updateSyncedLyrics(forceScroll = false) {
        if (!syncedLyrics.length) return;

        const nextIndex = findCurrentLyricIndex(audio.currentTime || 0);
        if (nextIndex === currentLyricIndex && !forceScroll) return;

        currentLyricIndex = nextIndex;

        const lines = lyricsContent.querySelectorAll('.lyric-line');
        lines.forEach((line, index) => {
            line.classList.toggle('current', index === currentLyricIndex);
            line.classList.toggle('past', index < currentLyricIndex);
        });

        if (lyricsFollow && currentLyricIndex >= 0) {
            const current = lines[currentLyricIndex];
            if (current) {
                lyricsProgrammaticScroll = true;

                // Scroll only the lyric body. scrollIntoView() can also move
                // ancestor containers, which may clip the toolbar at the top.
                const targetTop =
                    current.offsetTop
                    - (lyricsContent.clientHeight / 2)
                    + (current.offsetHeight / 2);

                lyricsContent.scrollTo({
                    top: Math.max(0, targetTop),
                    behavior: forceScroll ? 'auto' : 'smooth'
                });

                window.setTimeout(() => {
                    lyricsProgrammaticScroll = false;
                }, 500);
            }
        }
    }

    function pauseLyricsFollow() {
        if (!syncedLyrics.length) return;
        lyricsFollow = false;
        lyricsFollowBtn.classList.remove('active');
        lyricsReturnBtn.style.display = 'block';
    }

    function resumeLyricsFollow() {
        if (!syncedLyrics.length) return;
        lyricsFollow = true;
        lyricsFollowBtn.classList.add('active');
        lyricsReturnBtn.style.display = 'none';
        updateSyncedLyrics(true);
    }

    function toggleLyricsFocus() {
        lyricsFocus = !lyricsFocus;
        lyricsBox.classList.toggle('focus-mode', lyricsFocus);
        lyricsFocusBtn.classList.toggle('active', lyricsFocus);

        if (lyricsFollow) updateSyncedLyrics(true);
    }

    lyricsContent.addEventListener('wheel', () => {
        if (!lyricsProgrammaticScroll) pauseLyricsFollow();
    }, { passive: true });

    lyricsContent.addEventListener('touchmove', () => {
        if (!lyricsProgrammaticScroll) pauseLyricsFollow();
    }, { passive: true });

    audio.addEventListener('timeupdate', () => updateSyncedLyrics(false));
    audio.addEventListener('seeked', () => updateSyncedLyrics(true));

    async function playSong(el, idx) {
        currentIndex = idx;
        songElems.forEach(s => s.classList.remove('playing')); el.classList.add('playing');
        const songName = el.querySelector('.s-name').innerText;
        const songSrc = el.getAttribute('data-src');
        document.getElementById('play-title').innerText = songName;
        document.title = `${songName} // mp3-dana`;
        audio.src = songSrc; audio.play();

        resetLyrics();
        lyricsBox.style.display = 'none';

        try {
            const res = await fetch(`?root=<?php echo urlencode($rootKey); ?>&info=${encodeURIComponent(songSrc)}`);
            const data = await res.json();

            if (data.lrc && data.lrc.trim().length > 0) {
                const parsed = parseLrc(data.lrc);

                if (parsed.length > 0) {
                    renderSyncedLyrics(parsed);
                } else if (data.lyrics && data.lyrics.trim().length > 0) {
                    renderPlainLyrics(data.lyrics);
                }
            } else if (data.lyrics && data.lyrics.trim().length > 0) {
                renderPlainLyrics(data.lyrics);
            }
        } catch(e) {
            resetLyrics();
            lyricsBox.style.display = 'none';
        }
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
