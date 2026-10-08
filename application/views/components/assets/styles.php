<?php
// Shared head assets only. Per-module CSS scopes still decide what is styled.
// A global false also avoids all external icon/font requests.
if (($pkStyling['enabled'] ?? false) !== true) {
    return;
}
?>
<link rel="stylesheet"
      data-pk-font-awesome
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
      crossorigin="anonymous"
      referrerpolicy="no-referrer">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&amp;display=swap"
      rel="stylesheet"
      data-pk-poppins
      referrerpolicy="no-referrer">
