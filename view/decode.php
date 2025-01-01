<?php

use function Safe\file_put_contents;

require_once dirname(__FILE__) . '/../videos/configuration.php';


function listFolderFilesRec($dir, $extension = "php")
{
    $dir = rtrim($dir, "/");
    $ffs = scandir($dir);

    unset($ffs[array_search('.', $ffs, true)]);
    unset($ffs[array_search('..', $ffs, true)]);

    // prevent empty ordered elements
    if (count($ffs) < 1)
        return [];

    $files = [];

    foreach($ffs as $ff){
        $filename = $dir."/".$ff;
        //echo "Try: ".$filename . "<br>";
        if(is_dir($filename)) {
            $files = array_merge($files, listFolderFilesRec($filename));
        } else {
            // = basename($filename, ".php");
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            //echo "Ext: ".$ext . "<br>";
            if($ext == $extension)
                $files[] = $filename;
        }
    }
    return $files;
}

function unphp($file)
{
    $baseName = basename($file);

    $filesize = filesize($file);
    $postfields = array(
        //"file"=>"@".$file,
        "file" => new \CurlFile($file),
        "api_key"=>"2a30bd6cbade4a9dae31773e97ec216a",
    );
    $headers = array("Content-Type:multipart/form-data"); // cURL headers for file uploading

    $options = array(
        //CURLOPT_HEADER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $postfields,
        CURLOPT_INFILESIZE => $filesize,
        CURLOPT_RETURNTRANSFER => true
    ); // cURL options

    $ch = curl_init('https://www.unphp.net/api/v2/post');

    curl_setopt_array($ch, $options);

    $response = curl_exec($ch);

    if(!curl_errno($ch))
    {
        //echo "<code>" . print_r($response, true) . "</code>";
        
        $response = json_decode($response, true);

        //echo "<code>" . print_r($response, true) . "</code>";
    } else {
        $response = false;
    }
    curl_close($ch);
    return $response;
}

function hex2str($hex) {
    $str = '';
    for($i=0; $i<strlen($hex); $i+=2) 
        $str .= chr(hexdec(substr($hex,$i,2)));
    return $str;
}

$file_errors = [];
$files_converted = [];

function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';

    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }

    return $randomString;
}

function randomFnName() {
    $str = generateRandomString(10);
    $sufix = uniqid();
    return "fn_" . $str . $sufix;
}

if(isset($_POST['decodePath']) && !empty($_POST['decodePath']))
{

    $path = $_POST['decodePath'];
    //echo $path . "<br>";   

    $startTime = microtime(true);

    $files = listFolderFilesRec($path);

    foreach ($files as $filename) {
        //$fileEx = basename($filename, ".php");
        $response = unphp($filename);
        if($response !== false){
            $decoded = file_get_contents($response['output']);

            $dest_ilename = $filename . ".original";
            
            $php_Crypt2 = randomFnName();
            $fn_he = randomFnName();
            $original = "";

            if (str_contains($decoded, 'phpCrypt3')) { 
                $decoded = str_replace('phpCrypt3', $php_Crypt2, $decoded);
            } else
            if (str_contains($decoded, 'phpCrypt2')) { 
                $decoded = str_replace('phpCrypt2', $php_Crypt2, $decoded);
            } else 
            if (str_contains($decoded, 'phpCrypt')) { 
                $decoded = str_replace('phpCrypt', $php_Crypt2, $decoded);
            }
            
            $decoded = str_replace('he(', $fn_he."(", $decoded);
            $decoded = str_replace('eval($_)', '$original = $_', $decoded);

            eval($decoded);

            $original = rtrim($original);

            $original = str_replace('?><?php', "<?php", $original);

            file_put_contents($filename, $original);
            //echo "<code>" . $decoded . "</code>";
            $files_converted[] = $filename;
        } else {
            $file_errors[] = $filename;
        }
        sleep(1);
    }
    $endTime = microtime(true);
    $executionTime = $endTime - $startTime;
}

?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['language']; ?>">
    <head>
        <title><?php echo __("Decode") . $config->getPageTitleSeparator() . $config->getWebSiteTitle(); ?></title>
        <?php
        include $global['systemRootPath'] . 'view/include/head.php';
        ?>
    </head>
    <body>
        <?php
        include $global['systemRootPath'] . 'view/include/navbar.php';
        ?>
        <div class="container-fluid">
            <div class="panel">
                <div class="panel-body">
                    <div class="row">
                        <form class="form-horizontal" method="post">
                            <div class="col-md-8">
                                <label for="decodePath">Path of files to decrypt</label>
                                <input class="form-control" type="text" name="decodePath" id="decodePath">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-success">Decrypt</button>
                            </div>
                        </form>                        
                    </div>
                    <?php 
                        if(isset($_POST['decodePath']) && !empty($_POST['decodePath']))
                        {
                            $formattedTime = number_format($executionTime, 3, '.', '');
                            echo "Execution time: " . $formattedTime . " seconds";

                            if(!empty($files_converted)){
                                echo "<h2>Converted files</h2>";
                                foreach($files_converted as $file){
                                    echo "<code>{$file}</code><br>";
                                }
                            }

                            if(!empty($file_errors)){
                                echo "<h2>Falied files</h2>";
                                foreach($file_errors as $file){
                                    echo "<code>{$file}</code><br>";
                                }
                            }

                        }
                    ?>
                </div>
            </div>
            
        </div>
        <?php
        include $global['systemRootPath'] . 'view/include/footer.php';
        ?>  
    </body>
</html>
