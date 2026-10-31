# GCash payment plan

Status on 2026-10-31: planned, nothing built. Run the phone test first, because
its result decides how Phase 2 matches a payment to a booking.

## Goal

Online bookings are paid by GCash only. Cash stays at the desk. A booking is
confirmed only after its payment is verified.

## Flow

1. The player picks a slot and the site holds it for 15 minutes.
2. The player pays in GCash and pastes the reference number into the site.
3. The phone holding the GCash account reports the received money to the site.
4. If the reference and amount match, the booking confirms with no admin step.
5. If nothing matches, the booking waits for an admin to approve or reject it.

## Run this test first

Use a different GCash account from the one receiving the money, and scan
[gcash-test-qr.png](gcash-test-qr.png) from a computer screen. Both codes pay the
same account as the QR on the site, with PHP 1.37 set.

1. Scan TEST A. Note whether PHP 1.37 fills in by itself and whether it can be changed.
2. If TEST A gives an error, scan TEST B and note the same things.
3. Send the PHP 1.37.
4. On the receiving phone, do not tap the notification. Screenshot it from the
   drop-down and note whether it shows the amount and the reference number.

| Question | Result |
|---|---|
| Is the receiving phone Android? | |
| Does TEST A fill in the amount? | |
| Does TEST B fill in the amount? | |
| Does the notification show the amount? | |
| Does the notification show the reference number? | |

What the result decides:

- **Reference in the notification:** match on reference plus amount, as in the flow above.
- **No reference in it:** give each hold a unique amount (PHP 250.37) and match on that.
- **Amount fills in:** generate a QR per booking with the exact amount.
- **Amount does not fill in:** keep the current QR and show the amount to type.
- **iPhone:** Phase 2 cannot run, because iOS does not let an app read another
  app's notifications. Phase 1 still works.

## Phase 1: needs no phone app

- Remove cash from the public booking form (`app/Views/pages/booking.php`), the
  quick-book card (`app/Views/partials/book_modal.php`) and the Open Play sign-up
  (`app/Controllers/OpenPlayPage.php`, `app/Views/pages/open_play.php`).
- Keep cash at the desk: `app/Controllers/Admin/Walkins.php` and `Admin/Payments.php`.
- Online bookings accept `gcash` only (`app/Controllers/Booking.php`).
- Online bookings start `pending` and `unpaid`. Only a verified payment may mark
  one paid (`app/Services/Bookings.php`).
- Add the 15-minute hold. `Availability::occupiedSlots` already counts `pending`
  as taken. Release expired holds on read, with no cron, the way memberships expire.
- Allow one active hold per phone number.
- Require the GCash reference, unique across all bookings.
- Add approve and reject to `admin/payments`.
- Make the confirmation page show the real state. It always says "Confirmed" today
  (`app/Views/pages/booking_confirmed.php`).

## Phase 2: automatic confirmation

- Build a small Android app with a `NotificationListenerService` that reads
  `com.globe.gcash.android` only and posts the notification to a signed webhook.
  Never read SMS for this, because an SMS can be spoofed.
- Keep the webhook secret in `.env` only. This repository is public.
- Add a table of received payments: unique reference, amount, raw text, matched booking.
- Match in either arrival order, since the player's reference and the phone's
  report can come in either order.
- Keep the raw text, so the parser can be fixed if GCash changes its wording.
- Phase 1 approval stays as the fallback for when the phone is off or offline.

A hosted gateway such as PayRex can replace this phase later with its own signed
webhook. This project has no composer, so call any such API with CI4's
`CURLRequest` and `hash_hmac`.

## Decided against

- `kichuu/gcash-deeplink`: needs a third party's merchant ID and targets merchant QRs.
- `pppscn/SmsForwarder`: its README forbids commercial use.
- Third-party listener APKs: notification access exposes everything on the phone,
  so a small app of our own is safer.

## Still to decide

- Keep the 15-minute hold, or leave the slot open until approval? The hold is
  recommended: with an open slot, two people can pay for the same hour and one
  needs a manual refund.
