# Browser dependencies shipped with PK DTS

These files are hosted by the office web server, not loaded from the public
internet. Do not replace their asset URLs with CDN URLs.

- **Bootstrap 5.3.3**: official `twbs/bootstrap` tag v5.3.3 dist CSS and
  bundle JS. MIT license: `Bootstrap-MIT.txt`.
- **Font Awesome Free 6.7.2**: official `FortAwesome/Font-Awesome` tag 6.7.2
  all.min.css and WOFF2 fonts. The CSS paths are changed to `../../fonts/`
  and unused TTF fallbacks are omitted; the original solid WOFF2 font already
  shipped with PK DTS. Font Awesome license:
  `../fonts/fontawesome-LICENSE.txt`.
- **Chart.js 4.4.7**: the UMD browser distribution of the same version used by
  the prior CDN URL. The bundle was fetched from a checked-in copy at
  `MsVlogsm/ISP-Mikrotik-Billing-OneClick/public/xlink-dashboard/chart.umd.min.js.download`.
  The bundled header declares version 4.4.7. MIT license:
  `Chartjs-MIT.txt`.
- **Poppins 400, 500, 600, 700, 800**: official
  `google/fonts/ofl/poppins` TTF files. SIL Open Font License:
  `Poppins-OFL.txt`. `font-display: swap` keeps text visible on slow links.

Source-map comments were removed from vendor CSS/JS to avoid browser requests
for unshipped `.map` files.

Only static browser UI files are made available locally. This does **not**
cache any document, session, authentication response, or user records.
