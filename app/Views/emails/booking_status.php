<?php
/**
 * The email a player gets when the desk approves or rejects a booking. It reads
 * like the ticket it is: what, when, and the reference to show at the desk.
 *
 * Tables and inline styles, because Gmail and Outlook drop the rest. Colours are
 * the site's own tokens from app.css, and the fonts fall back to what a phone
 * already has, since a mail client will not load Archivo or Chivo Mono.
 *
 * @var bool        $approved
 * @var string      $reference
 * @var string      $court
 * @var string      $day      "Sunday, 1 November 2026", the real calendar day
 * @var string      $time     "12:00 AM–1:00 AM"
 * @var string      $total
 * @var string|null $reason
 * @var string      $link
 * @var string      $phone
 * @var string      $address
 * @var string      $logo
 */
$ink    = '#0B2410';
$mute   = '#3F6647';
$line   = '#9DFDA5';
$mint   = '#EDFFEF';
$accent = '#07FA1B';
$red    = '#C0262D';
$sans   = "font-family:Archivo,Arial,Helvetica,sans-serif;";
$mono   = "font-family:'Chivo Mono',Consolas,'Courier New',monospace;";
$label  = $mono . "font-size:11px;letter-spacing:1px;text-transform:uppercase;color:{$mute};";
$status = $approved ? '&#9650;&nbsp;Confirmed' : '&#10005;&nbsp;Not approved';
$title  = $approved ? 'Your court is booked.' : 'We could not approve this booking.';
$lede   = $approved
    ? 'Your GCash payment checked out. Show the reference below at the desk when you arrive.'
    : 'The desk could not approve it, so this booking has been cancelled.';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light only">
<title><?= esc($title) ?></title>
</head>
<body style="margin:0;padding:0;background:#FFFFFF;">
<div style="display:none;max-height:0;overflow:hidden;color:#FFFFFF;"><?= esc($court . ', ' . $day . ', ' . $time) ?></div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#FFFFFF;">
  <tr>
    <td align="center" style="padding:16px 12px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;border:1px solid <?= $ink ?>;">

        <tr>
          <td style="background:<?= $ink ?>;padding:12px 16px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td width="42" valign="middle"><img src="<?= esc($logo, 'attr') ?>" width="34" height="34" alt="" style="display:block;border:0;"></td>
                <td valign="middle" style="<?= $sans ?>font-size:16px;font-weight:bold;color:#FFFFFF;">Go Paddle</td>
                <td valign="middle" align="right" style="<?= $mono ?>font-size:12px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:<?= $approved ? $accent : '#FFFFFF' ?>;white-space:nowrap;"><?= $status ?></td>
              </tr>
            </table>
          </td>
        </tr>
        <?php if (! $approved): ?>
          <tr><td height="4" style="background:<?= $red ?>;font-size:0;line-height:0;">&nbsp;</td></tr>
        <?php endif; ?>

        <tr>
          <td style="padding:24px 20px 20px;">
            <div style="<?= $sans ?>font-size:26px;line-height:1.2;font-weight:bold;letter-spacing:-0.3px;color:<?= $ink ?>;"><?= esc($title) ?></div>
            <div style="<?= $sans ?>font-size:15px;line-height:1.55;color:<?= $mute ?>;padding-top:8px;"><?= esc($lede) ?></div>
          </td>
        </tr>

        <tr>
          <td style="background:<?= $mint ?>;border-top:2px solid <?= $ink ?>;border-bottom:1px solid <?= $line ?>;padding:18px 20px;">
            <div style="<?= $label ?>"><?= esc($court) ?></div>
            <div style="<?= $mono ?>font-size:24px;line-height:1.25;font-weight:bold;color:<?= $ink ?>;padding-top:6px;"><?= esc($time) ?></div>
            <div style="<?= $sans ?>font-size:15px;color:<?= $ink ?>;padding-top:4px;"><?= esc($day) ?></div>
          </td>
        </tr>

        <tr>
          <td style="padding:6px 20px 4px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td style="<?= $label ?>padding:12px 0;border-bottom:1px solid <?= $line ?>;">Reference</td>
                <td align="right" style="<?= $mono ?>font-size:15px;font-weight:bold;color:<?= $ink ?>;padding:12px 0;border-bottom:1px solid <?= $line ?>;"><?= esc($reference) ?></td>
              </tr>
              <tr>
                <td style="<?= $label ?>padding:12px 0;"><?= $approved ? 'Paid by GCash' : 'Booking total' ?></td>
                <td align="right" style="<?= $mono ?>font-size:15px;font-weight:bold;color:<?= $ink ?>;padding:12px 0;"><?= esc($total) ?></td>
              </tr>
            </table>
          </td>
        </tr>

        <?php if (! $approved): ?>
          <tr>
            <td style="padding:0 20px 8px;">
              <div style="border-left:3px solid <?= $red ?>;padding:2px 0 2px 12px;">
                <div style="<?= $label ?>">Reason</div>
                <div style="<?= $sans ?>font-size:15px;line-height:1.5;color:<?= $ink ?>;padding-top:4px;"><?= esc($reason) ?></div>
              </div>
            </td>
          </tr>
        <?php endif; ?>

        <tr>
          <td style="padding:12px 20px 24px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td style="background:<?= $accent ?>;border-radius:2px;">
                  <a href="<?= esc($link, 'attr') ?>" style="display:inline-block;<?= $mono ?>font-size:12px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:<?= $ink ?>;text-decoration:none;padding:14px 22px;">View booking</a>
                </td>
              </tr>
            </table>
            <?php if (! $approved): ?>
              <div style="<?= $sans ?>font-size:14px;line-height:1.5;color:<?= $mute ?>;padding-top:14px;">Think this is a mistake? Call the desk on <?= esc($phone) ?>.</div>
            <?php endif; ?>
          </td>
        </tr>

        <tr>
          <td style="border-top:1px solid <?= $line ?>;padding:12px 20px;<?= $sans ?>font-size:12px;line-height:1.5;color:<?= $mute ?>;">
            <?= esc($address) ?><br><?= esc($phone) ?>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>
</body>
</html>
