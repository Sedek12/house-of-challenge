<?php
// Script TEMPORAIRE : extrait vendor-upload.zip (à la racine du projet) puis se supprime. À ne pas laisser sur le serveur.
if (($_GET['k'] ?? '') !== 'ocd-7f3k29x') {
    http_response_code(403);
    exit('Interdit');
}
@set_time_limit(300);
$root = dirname(__DIR__);
$zipFile = $root.'/vendor-upload.zip';
if (! class_exists('ZipArchive')) {
    exit('ZipArchive indisponible sur ce serveur.');
}
if (! is_file($zipFile)) {
    exit('vendor-upload.zip introuvable dans : '.$root);
}
$zip = new ZipArchive;
$r = $zip->open($zipFile);
if ($r !== true) {
    exit('Impossible d\'ouvrir le zip. Code erreur : '.$r.' | taille reçue sur le serveur : '.filesize($zipFile).' octets (attendu : '.($_GET['s'] ?? '?').' octets). Si les deux tailles diffèrent, l\'envoi est incomplet : renvoyez le zip en mode binaire.');
}
$ok = $zip->extractTo($root);
$n = $zip->numFiles;
$zip->close();
if ($ok) {
    @unlink($zipFile);
    @unlink(__FILE__);
    foreach (['packages.php', 'services.php'] as $f) {
        @unlink($root.'/bootstrap/cache/'.$f);
    }
    exit("Terminé : $n éléments extraits dans $root/vendor. Le zip et ce script ont été supprimés. Rechargez le site.");
}
exit('Échec de l\'extraction (droits d\'écriture ?).');
