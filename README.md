# mp3-dana (MP3棚)
A minimalist, folder-based web music player in a single PHP file.  
PHP 1ファイルで動作する、フォルダ構造を活かした軽量ミュージックプレイヤー。

<p align="center">
  <img src="screenshot.png" width="800" alt="mp3-dana screenshot" style="border-radius: 12px; border: 1px solid #333; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
  <br>
  <i>Dark Mode: Circle view (Left) and Square/List view (Right)</i>
</p>

## Concept / コンセプト
**"A simple shelf for your music."** **「音楽のための、シンプルな棚」**

mp3-dana is a tiny script that turns your messy music directories into a functional web player instantly. No database, no complex installation—just drop and play.  
mp3-danaは、音楽フォルダを即座にWebプレイヤー化する軽量スクリプトです。データベースは不要。ファイルを置いて読み込むだけで、あなたの音楽ライブラリをブラウザで楽しめます。

## Features / 特徴
- **Zero Config**: Single-file PHP script (+ ID3 library).  
  **ゼロ設定**: PHP 1ファイルで動作（+ ID3ライブラリ）。
- **Folder-Centric**: Respects your directory structure.  
  **フォルダ中心**: フォルダ構造をそのままリスペクト。
- **Rich Metadata**: Automatic extraction of cover art and lyrics from ID3 tags.  
  **豊かなメタデータ**: ID3タグからジャケット画像や歌詞を自動抽出。
- **Theme Support**: Dark and Light modes.  
  **テーマ対応**: ダーク・ライトモード切替。
- **Private & Safe**: Designed for intranet/LAN use.  
  **プライベート設計**: イントラネット・個人利用に最適化。
- **QR Sync**: (Optional) Scan to sync folders to your phone.  
  **QR同期**: QRコードでスマホへの引き継ぎ（任意）。
- **Synchronized Lyrics (.lrc)**: If a `.lrc` file with the same basename as the audio file is placed in the same folder, mp3-dana displays synchronized lyrics that follow playback. The current line is highlighted and automatically scrolled into view. Clicking a lyric line seeks playback to that timestamp.  
  **同期歌詞（.lrc）対応**: 音声ファイルと同じフォルダに、同じベース名の `.lrc` ファイルを配置すると、再生位置に同期して歌詞を表示します。現在行をハイライトし、自動スクロールします。歌詞行をクリックすると、その時刻へシークできます。

## Installation / 設置方法

1. **Download index.php** to your web directory.  
   `index.php` をWeb公開ディレクトリに配置します。

2. **Configure Folders**: Open `index.php` and update the `$allowedRoots` section with your music folder paths.  
   `index.php` を開き、`$allowedRoots` セクションをご自身の音楽フォルダのパスに合わせて書き換えます。

3. **Install getID3**: Place the [getID3](https://www.getid3.org/) library in a directory named `/getid3` (next to `index.php`).  
   [getID3](https://www.getid3.org/) ライブラリを `/getid3` という名前のフォルダで配置します（`index.php` と同じ階層）。

4. **Optional QR Sync**: (Optional) Place `qrcode.min.js` next to `index.php`.  
   （任意）`qrcode.min.js` を `index.php` と同じ階層に配置するとQRコード同期が有効になります。

5. **Access via Browser**: Open the directory in your web browser.  
   ブラウザでアクセスして完了です。

## Synchronized Lyrics / 同期歌詞

mp3-dana supports standard LRC lyric files.

mp3-danaは、標準的なLRC形式の同期歌詞に対応しています。

Place the `.lrc` file in the same directory as the audio file and use the same basename.

`.lrc` ファイルは音声ファイルと同じフォルダに置き、拡張子以外のファイル名を同じにしてください。

Example / 例:

```text
01-Song.mp3
01-Song.lrc
```

Example LRC content / LRCファイル例:

```lrc
[00:12.50]First lyric line
[00:16.20]Second lyric line
[00:20.10]Third lyric line
```

When synchronized lyrics are available:

- The current lyric line is highlighted.
- Lyrics automatically scroll with playback.
- Clicking a lyric line seeks playback to that timestamp.
- Manual scrolling temporarily disables automatic following.
- The `FOLLOW` button returns to the current playback position.
- The `FOCUS` button provides a simplified current-line-oriented view.
- `[offset:]` in LRC files is supported.

同期歌詞がある場合は、以下の機能を利用できます。

- 現在再生中の歌詞行をハイライト
- 再生位置に合わせた自動スクロール
- 歌詞行クリックによるシーク
- 手動スクロール時の自動追従停止
- `FOLLOW` ボタンによる現在位置への復帰
- `FOCUS` ボタンによる簡易表示
- LRCの `[offset:]` に対応

If no matching `.lrc` file exists, mp3-dana falls back to lyrics embedded in the ID3 tag as before.

同名の `.lrc` ファイルが存在しない場合は、従来どおりID3タグ内の歌詞を表示します。


## Optional Features / 追加機能

You can unlock these powerful tools by adding small external libraries.  
外部ライブラリを追加することで、以下の便利な機能が利用可能になります。

- **QR Sync (via [qrcodejs](https://github.com/davidshimjs/qrcodejs))** Scan the QR code to instantly sync and open the current folder on your mobile device. Perfect for "handing over" your music from PC to phone.  
  **QR同期**: QRコードをスキャンして、現在開いているフォルダを即座にスマホで共有。PCからスマホへの「再生の引き継ぎ」に最適です。

## Notes & Limitations / 注意事項と制限

- **Initial Loading Time** Since this app scans the directory and extracts album art on the fly, the first access to a folder may take some time as it generates a cache (`cover_cache.jpg`).  
  **初回の読み込み時間**: アクセス時にディレクトリをスキャンしてアルバムアートを抽出するため、キャッシュ（`cover_cache.jpg`）を生成する初回アクセス時のみ表示に時間がかかる場合があります。

- **Metadata Dependence** The visual richness of your "shelf" depends on your ID3 tags. We recommend using tools like Mp3tag to organize your library.  
  **メタデータへの依存**: 「棚」の賑やかさは、ファイルのID3タグに依存します。快適な利用のために、Mp3tagなどのツールでライブラリを整理しておくことをお勧めします。

- **No Playlist Feature** Designed to let you enjoy music "album by album," just like picking a CD from a shelf. Does not support cross-folder playlists.  
  **プレイリスト機能なし**: 棚からCDを選ぶように「アルバム単位」で音楽を楽しむスタイルに特化しています。フォルダを跨いだプレイリスト作成機能はありません。

- **File Permissions** The script needs write permission to create `cover_cache.jpg` in your music folders.  
  **書き込み権限**: キャッシュ画像を生成するため、音楽フォルダへの書き込み権限が必要です。

- **LRC Timing Accuracy**: Lyric synchronization depends on the timestamps stored in the `.lrc` file. mp3-dana does not automatically analyze vocals or generate timestamps.  
  **LRCの同期精度**: 歌詞の同期精度は `.lrc` ファイル内のタイムスタンプに依存します。mp3-dana自身が音声解析してタイムスタンプを生成する機能はありません。


## Security Notice / セキュリティについて
This app is for **private intranet use only**. It has no authentication.  
本ツールは**イントラネット内での個人利用**を想定しています。認証機能はありませんので、インターネット上への直接公開は避けてください。

## License
MIT License

