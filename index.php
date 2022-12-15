<?php
// get root dirs
function get_root_dirs()
{
    $root_dirs = glob(ROOT . '/*', GLOB_ONLYDIR | GLOB_NOSORT);

    if (empty($root_dirs)) return array();
    return array_filter($root_dirs, function ($dir) {
        return !is_exclude($dir, true, is_link($dir));
    });
}
function get_file_list($path)
{
    $path = ROOT . '/' . $path;
    $arr = [
        'dir' => [],
        'file' => [],
        'link' => [],
    ];
    if (is_dir($path)) {
        if ($dh = opendir($path)) {
            while (($file = readdir($dh)) !== false) {
                if (in_array(substr($file, 0, 1), ['.', '#'])) continue;
                
                if (in_array($file, ['_files', 'index.php'])) continue;

                $file_path = $path . '/' . $file;
                if (is_dir($file_path)) {
                    $arr['dir'][] = $file;
                } elseif (is_file($file_path)) {
                    $arr['file'][] = $file;
                } elseif (is_link($file_path)) {
                    $arr['link'][] = $file;
                }
            }
            closedir($dh);
        } else {
            return false;
        }
    } else {
        return false;
    }

    return $arr;
}

function array_page($array, $page = 1, $per_page = 24)
{
    $start = ($page - 1) * $per_page;

    return array_slice($array, $start, $per_page);
}

function get_page_html($total, $page = 1, $per_page = 24)
{
    global $current_dirs;

    $total_pages = ceil($total / $per_page);



    if ($total_pages < 2) {
        return false;
    }
    $html = '<nav class="pager"><ul class="pagination">';
    if ($page > 4) {
        $html .= "<li><a href=\"?s={$current_dirs}&page=1\">首页</a></li>";
    }
    if ($page > 4) {
        $start_key = (int)$page - 4;
        $end_key = (int)$page + 4;
    } else {
        $start_key = 1;
        $end_key = 9;
    }

    if ($page + 4 > $total_pages) {
        $end_key = $total_pages;
        $start_key = $end_key - 9;
    }
    if ($start_key < 1) {
        $start_key = 1;
    }
    if ($end_key > $total_pages) {
        $end_key = $total_pages;
    }
    for ($i = $start_key; $i <= $end_key; $i++) {
        if ((int)$page == $i) {
            $html .= "<li class=\"active\"><span>$i</span></li>";
        } else {
            $html .= "<li><a href=\"?s={$current_dirs}&page={$i}\">$i</a></li>";
        }
    }

    if ($total_pages > $page + 4) {
        $html .= "<li class=\"disabled\"><span>...</span></li>";
    }
    if ($total_pages > 9) {
        $html .= "<li><a href=\"?s={$current_dirs}&page={$total_pages}\">尾页</a></li>";
    }
    $html .= '</ul></nav>';
    return $html;
}

function input($key = null, $method = 'get', $default = null, $verify = null)
{
    if ($method == 'get') {
        $val = isset($_GET[$key]) ? $_GET[$key] : $default;
    } else if ($method == 'post') {
        $val = isset($_POST[$key]) ? $_POST[$key] : $default;
    }
    if ($verify) {
        if (!call_user_func($verify, $val)) {
            return false;
        }
    }
    return $val;
}
// is exclude
function is_exclude($path = false, $is_dir = true, $symlinked = false)
{
    global $CONFIG;
    // early exit
    if (!$path || $path === ROOT) return;

    // exclude all root-relative paths that start with /_files* (reserved for any files and folders to be ignored and hidden from Files app)
    if (strpos('/' . root_relative($path), '/_files') !== false) return true;

    // exclude files PHP application
    if ($path === __FILE__) return true;

    // symlinks not allowed
    if ($symlinked && !$CONFIG['allow_symlinks']) return true;

    // exclude storage path
    if ($CONFIG['storage_path'] && is_within_path($path, $CONFIG['storage_path'])) return true;

    // dirs_exclude: check root relative dir path
    if ($CONFIG['dirs_exclude']) {
        $dirname = $is_dir ? $path : dirname($path);
        if ($dirname !== ROOT && preg_match($CONFIG['dirs_exclude'], substr($dirname, strlen(ROOT)))) return true;
    }

    // files_exclude: check vs basename
    if (!$is_dir) {
        $basename = basename($path);
        if ($CONFIG['files_exclude'] && preg_match($CONFIG['files_exclude'], $basename)) return true;
    }
}
function root_relative($dir)
{
    return ltrim(substr($dir, strlen(ROOT)), '\/');
}
function is_within_path($path, $root)
{
    return strpos($path . '/', $root . '/') === 0;
}


function get_url_path($dir)
{
    global $CONFIG;
    if (!is_within_docroot($dir)) return false;

    // if in __dir__ path, __dir__ relative
    if (is_within_path($dir, ROOT)) return $dir === ROOT ? '.' : substr($dir, strlen(ROOT) + 1);

    // doc root, doc root relative
    return $dir === $CONFIG['doc_root'] ? '/' : substr($dir, strlen($CONFIG['doc_root']));
}

function is_within_docroot($path)
{
    global $CONFIG;
    return is_within_path($path, $CONFIG['doc_root']);
}

function real_path($path)
{
    $real_path = realpath($path);
    return $real_path ? str_replace('\\', '/', $real_path) : false;
}

function generate_thumb($filename, $thumbname) {
    if (!file_exists('_files/thumbs')) {
        mkdir('_files/thumbs');
    }
    if (extension_loaded('imagick')) {
        $image = new Imagick($filename);
        $image->thumbnailImage(THUMB_W);
        $image->writeImage($thumbname);
        $image->destroy();
    } else {
        $image = imagecreatefromstring(file_get_contents($filename));
        $img_w = imagesx($image);
        $img_h = imagesy($image);
        $thumb_w = THUMB_W;
        $thumb_h = THUMB_H;
        if ($img_w > $img_h) {
            $thumb_w = THUMB_W;
            $thumb_h = intval($img_h / $img_w * THUMB_W);
        } else if ($img_w < $img_h) {
            $thumb_w = intval($img_w / $img_h * THUMB_H);
            $thumb_h = THUMB_H;
        }
        $thumb = imagecreatetruecolor($thumb_w, $thumb_h);
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $thumb_w, $thumb_h, $img_w, $img_h);
        imagejpeg($thumb, $thumbname);
        imagedestroy($thumb);
        imagedestroy($image);
    }
}
function get_thumb($file){
    
    $file_path = ROOT . '/' .$file;
    if(filesize($file_path) > 1024*1024*20 ){
        return "https://iph.href.lu/800x600?text=内容过大无法预览";
    }
    $thumb_path = '_files/thumbs/'.md5_file($file_path).'.jpg';

    if(!is_file($thumb_path)){
        generate_thumb($file_path,$thumb_path);
    }

    return $thumb_path;

}

function get_file_ext($file){
    return strrchr(strtolower($file), '.');
}

function get_breadcrumb($path){
    $path_array = explode("/",$path);

    $html = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
            $html .= "<li class=\"breadcrumb-item\"><a href=\"/\"><i class=\"bi bi-house\"></i></a></li>";
            foreach($path_array as $i => $item){
                $url = implode('/',array_slice($path_array, 0,  $i+1));
                if(end($path_array) == $item){
                    $html .= "<li class=\"breadcrumb-item active\" aria-current=\"page\">{$item}</li>";
                }else{
                    $html .= "<li class=\"breadcrumb-item\"><a href=\"?s={$url}\">{$item}</a></li>";
                }
                
            }

    $html .= '</ol></nav>';

    echo $html;
}

function get_file_icons($ext){
    $icons = "";
    switch ($ext) {
        case '.txt':
            $icons = "bi-file-earmark-text";
            break;
        case '.pdf':
            $icons = "bi-file-earmark-pdf";
            break;
        case '.word':
            $icons = "bi-file-earmark-word";
            break;
        case '.ttf':
            $icons = "bi-filetype-ttf";
            break;
        case '.otf':
            $icons = "bi-filetype-otf";
            break;
        case '.heic':
            $icons = "bi-filetype-heic";
            break;
        default:
            $icons = "bi-file-earmark";
            break;
    }
    return $icons;
}

/**
 * +------------------------
 * |     这是入口开始执行
 * +------------------------
 */

// 获取当前文件的上级目录
define('WEBURL', "https://zfile.tool"); 
define("ROOT", dirname(__FILE__));
define('THUMB_W', 300);
define('THUMB_H', 1800); // Set thumbnail size in pixels

$exclude = [
    '.',
    '..',
    '_files',
    '_temps',
    'README.md',
];
// 扫描$con目录下的所有文件

$CONFIG = [

    'doc_root' => real_path($_SERVER['DOCUMENT_ROOT']),
    // cache
    'cache' => true,
    'cache_key' => 0,
    'storage_path' => '_files',

    // exclude files directories regex
    'files_exclude' => '',
    'dirs_exclude' => '',
    'allow_symlinks' => true,
];

//顶级目录列表
$root_dirs = get_root_dirs();

$S = input('s','get','');

$current_dirs = $S ? str_replace(['.', '../'], "", $S) : "";

$file_list =  get_file_list($current_dirs);
$page =  input('page', 'get', 1);

?>

<!doctype html>
<html lang="zh-CN">

<head>
    <meta charset="utf-8">
    <meta name="theme-color" content="#5d6146" />
    <meta name="renderer" content="webkit">
    <title>传硕公版书_传递文明的硕果_免费、合法、无需注册！</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="keywords" content="免费电子书,传硕,公版书,公版書,公共版权,公版书籍网站,无版权书籍,公版书籍查询,七秒古诗词,七秒读书,七秒电子书,电子书下载,二十四史下载">
    <meta name="description" content="本站共计收录了85万+篇的古诗词、近千篇文言文和一万多本中文公版电子书！且这些书籍都属于公共版权，您可以随意阅读、下载、分享、转发且不用担心任何版权问题！免费、合法、无需注册！">
    <link rel="shortcut icon" href="https://www.7sbook.com/assets/img/favicon.ico" />
    <link href="https://www.7sbook.com/assets/css/zpl.css?v=1.5.4" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/venobox@2.0.4/dist/venobox.min.css" />

    <script>
        var _hmt = _hmt || [];
        (function() {
            var hm = document.createElement("script");
            hm.src = "https://hm.baidu.com/hm.js?e4724966c10d71a2984d44a77eaf69ba";
            var s = document.getElementsByTagName("script")[0];
            s.parentNode.insertBefore(hm, s);
        })();
    </script>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-6790232035711874" crossorigin="anonymous"></script>

    <style>
        .root_dir {
            font-size: 1.2rem;
            line-height: 3rem;
        }
        .grid-item {
            /* width: 33.333%; */
            padding: 5px;
        }

        .grid-item .folder , .grid-item .file{
            text-align: center;
            background-color: #fff;
            border-radius: 6px;
            padding: 30px 0;
        }

        .grid-item .bi {
            font-size: 4rem;
            line-height: 5rem;
            color: #ff9800;
        }

        .grid-item .folder p {
            margin-bottom: 0;
        }
        .breadcrumb{
            margin-bottom: 0.2rem;
        }
    </style>
</head>


<body class="bg-beige">
    <header class="zpl-header py-3 py bg-olive">
        <div class="container">
            <div class="row flex-nowrap justify-content-between align-items-center zpl-header-main">
                <div class="col pt-1">
                    <a class="link-light" target="_blank" href="https://www.7sbook.com/donation">捐赠支持</a>
                </div>
                <div class="col-auto text-center">
                    <a class="zpl-header-logo text-white" href="https://www.7sbook.com/" title="传硕公版书-传递文明的硕果">传硕公版书</a>
                </div>
                <div class="col d-flex justify-content-end align-items-center">
                    <a class="btn btn-outline-light btn-sm" href="https://www.7sbook.com/search"><i class="bi bi-search"></i>&nbsp;搜索</a>
                </div>
            </div>
        </div>
    </header>
    <div class="nav-scroller bg-burlywood">
        <div class="container">
            <nav class="nav top-nav d-flex justify-content-between">
                <a href="https://www.7sbook.com/">首页</a>
                <a href="https://www.7sbook.com/ebook/index.html">书籍</a>
                <a href="https://www.7sbook.com/category/文章/">文章</a>
                <a href="https://www.7sbook.com/poetry/index.html">诗词</a>
                <a href="https://www.7sbook.com/author/index.html">作者</a>
                <a href="https://www.7sbook.com/category/名画/">名画</a>
            </nav>
        </div>
    </div>

    <div class="container-fluid container-lg mt-2">
        <!-- 全站导航栏广告 -->
        <ins class="adsbygoogle" style="display:block" data-ad-client="ca-pub-6790232035711874" data-ad-slot="9324099010" data-ad-format="auto" data-full-width-responsive="true"></ins>
        <script>
            (adsbygoogle = window.adsbygoogle || []).push({});
        </script>
    </div>

    
    <main class="container-fluid">

        <div class="row">
            <div class="col-md-2">
                <div class="mt-3 p-3 bg-burlywood rounded shadow-sm">
                    <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                        <h3 class="h6">主目录</h3>
                    </div>

                    <?php foreach ($root_dirs as $dir) { ?>
                        <div class="root_dir">
                            <a href="?s=<?= get_url_path($dir); ?>">
                                <i class="bi bi-folder"></i>
                                <span><?= get_url_path($dir); ?></span>
                            </a>
                        </div>
                    <?php } ?>
                </div>
            </div>
            <div class="col-md-10">
                <div class="mt-3 p-3 bg-burlywood rounded shadow-sm">
                    <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                    <?=get_breadcrumb($S);?>
                    </div>

                    <div class="grid">
                        <?php if ($page == 1) {
                            foreach ($file_list['dir'] as $file) { ?>
                                <div class="grid-item col-6 col-md-4 col-lg-3">
                                    <a href="?s=<?= $current_dirs ?"{$current_dirs}/":""; ?><?= $file; ?>">
                                        <div class="folder">
                                            <span class="bi bi-folder-fill"></span>
                                            <p><?= $file; ?></p>
                                        </div>
                                    </a>
                                </div>
                        <?php }
                        } ?>
                        

                        <?php foreach (array_page($file_list['file'], $page) as $file) {
                            $ext = strrchr(strtolower($file), '.'); ?>
                            <div class="grid-item col-6 col-md-4 col-lg-3">
                                <?php if (in_array($ext, ['.jpg', '.png', '.jpeg'])) { ?>
                                    <a class="my-image-links" data-gall="gallery01" href="/<?= $current_dirs; ?>/<?= $file; ?>">
                                        <img src="<?=get_thumb($current_dirs.'/'.$file); ?>" style="width: 100%;" />
                                    </a>
                                <?php } else { ?>

                                    <a href="/<?= $current_dirs; ?>/<?= $file; ?>">
                                        <div class="file">
                                            <span class="bi <?=get_file_icons($ext);?>"></span>
                                            <p><?= $file; ?></p>
                                        </div>
                                    </a>

                                <?php } ?>
                            </div>

                        <?php } ?>

                    </div>

                    <?php echo get_page_html(count($file_list['file']), $page); ?>
                </div>
            </div>


        </div>
    </main>
    <footer class="footer bg-burlywood">
        <div class="container">
            <ul class="bs-docs-footer-links">
                <li><a href="https://www.7sbook.com/chuanshuo-plan/">传硕计划</a></li>
                <li><a href="https://www.7sbook.com/privacy-policy/">隐私政策</a></li>
                <li><a href="https://www.7sbook.com/category/blog/">博客文章</a></li>
                <li><a href="https://www.7sbook.com/contact-us/">联系我们</a></li>
            </ul>
            <p class="copyright">Copyright&nbsp;©&nbsp;2021-2022 传硕公版书</p>

        </div>
    </footer>
    <script src="https://www.7sbook.com/assets/js/jquery-3.6.0.min.js"></script>
    <script src="https://www.7sbook.com/assets/js/bootstrap.min.js"></script>
    <script src="https://www.7sbook.com/assets/js/zpl.js?v=1.5.3"></script>

    <script src="https://cdn.jsdelivr.net/npm/masonry-layout@4.2.2/dist/masonry.pkgd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/imagesloaded@5.0.0/imagesloaded.pkgd.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/venobox@2.0.4/dist/venobox.min.js"></script>

    <script>
        var $grid = $('.grid').masonry({
            // options
            itemSelector: '.grid-item',
            percentPosition: true,
            // gutter: 10,
            // columnWidth: 200,
        });
        // layout Masonry after each image loads
        $grid.imagesLoaded().progress(function() {
            $grid.masonry('layout');
        });


        new VenoBox({
            selector: '.my-image-links',
            numeration: true,
            infinigall: true,
            share: true,
            spinner: 'rotating-plane'
        });
    </script>
</body>

</html>