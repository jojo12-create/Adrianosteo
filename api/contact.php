<?php
/* ==========================================================================
   POST /api/contact.php : demande de rappel du formulaire /contact/
   Gratuit : utilise mail() de PHP, disponible chez la plupart des hébergeurs
   (dont l'hébergement LiteSpeed actuel). Aucun service tiers.
   Réponse JSON si appelé en fetch, sinon redirection vers /contact/#envoye ou #erreur (messages affichés en CSS, sans JS).
   ========================================================================== */

const NOTIFY_TO = 'adrian.osteo38@gmail.com';   // destinataire des demandes
const FROM      = 'no-reply@osteovillefontaine.fr'; // adresse du domaine (meilleure délivrabilité)
const ALLOWED   = ['Un adulte', 'Un bébé ou un enfant', 'Une femme enceinte', 'Un sportif', 'Une personne âgée', 'Autre'];

$wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
function reply(bool $ok, int $code = 200, ?string $field = null, ?string $error = null): never {
  global $wantsJson;
  http_response_code($code);
  if ($wantsJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_filter(['ok' => $ok, 'field' => $field, 'error' => $error], fn ($v) => $v !== null), JSON_UNESCAPED_UNICODE);
  } else {
    header('Location: /contact/' . ($ok ? '#envoye' : '#erreur'), true, 303);
  }
  exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') reply(false, 405, null, 'Méthode non autorisée.');

$clean = fn (string $k, int $max) => mb_substr(trim(str_replace(["\r", "\n"], ' ', (string)($_POST[$k] ?? ''))), 0, $max);
$name    = $clean('name', 100);
$contact = $clean('contact', 120);
$for     = $clean('for', 60);
$message = mb_substr(trim((string)($_POST['message'] ?? '')), 0, 1000);

// Anti-robots : champ piège rempli ou envoi en moins de 3 secondes → on fait comme si tout allait bien
$t = (int)($_POST['t'] ?? 0);
if (($_POST['website'] ?? '') !== '' || ($t > 0 && (microtime(true) * 1000 - $t) < 3000)) reply(true);

// Validation (mêmes messages que dans le navigateur)
if (mb_strlen($name) < 2) reply(false, 422, 'name', 'Indiquez votre nom et prénom.');
$isEmail = filter_var($contact, FILTER_VALIDATE_EMAIL) !== false;
$isPhone = preg_match('/^(\+33|0033|0)[1-9]\d{8}$/', preg_replace('/[\s.\-()]/', '', $contact)) === 1;
if (!$isEmail && !$isPhone) reply(false, 422, 'contact', 'Ce numéro ou cet e-mail ne semble pas valide.');
if (!in_array($for, ALLOWED, true)) reply(false, 422, 'for', 'Choisissez la personne concernée par la séance.');
if (($_POST['consent'] ?? '') !== '1') reply(false, 422, 'consent', 'Cochez la case pour que je puisse vous recontacter.');

$subject = '=?UTF-8?B?' . base64_encode("Demande de rappel : $name") . '?=';
$body = "Nouvelle demande depuis le site.\n\nNom : $name\nContact : $contact\nSéance pour : $for\nMessage : " . ($message !== '' ? $message : '(aucun)') . "\n\nDate : " . date('d/m/Y H:i') . "\n";
$headers = ['From: Site osteovillefontaine.fr <' . FROM . '>', 'Content-Type: text/plain; charset=UTF-8', 'X-Mailer: PHP'];
if ($isEmail) $headers[] = 'Reply-To: ' . $contact;

$sent = @mail(NOTIFY_TO, $subject, $body, implode("\r\n", $headers));
$sent ? reply(true) : reply(false, 500, null, "L'envoi a échoué.");
