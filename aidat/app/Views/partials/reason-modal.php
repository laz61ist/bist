<?php
/** Gerekçe isteyen iptal/ters kayıt onayı. Kullanım: x-data="reasonModal" kapsayıcıda; buton @click="ask(url, başlık, etiket)". */
?>
<div class="modal" x-show="open" x-cloak @keydown.escape.window="open=false">
  <div class="overlay" @click="open=false"></div>
  <form class="modal-box" method="post" x-ref="form" style="position:relative" @submit.prevent="submit()">
    <?= csrf_field() ?>
    <div class="modal-head"><h3 x-text="title"></h3><button type="button" class="icon-btn" @click="open=false"><i class="bi bi-x-lg"></i></button></div>
    <div class="modal-body">
      <div class="alert warn mb-4"><i class="bi bi-exclamation-triangle"></i><div>Finansal kayıtlar silinmez; <strong>ters kayıt</strong> oluşturulur ve gerekçe işlem izine yazılır. Bu işlem geri alınamaz.</div></div>
      <div class="field"><label for="reason-input" x-text="label"></label><textarea id="reason-input" class="textarea" name="reason" x-model="reason" rows="3" required minlength="3" placeholder="Örn. mükerrer kayıt, yanlış bölüm seçildi…"></textarea><div class="help">En az 3 karakter.</div></div>
    </div>
    <div class="modal-foot"><button type="button" class="btn" @click="open=false">Vazgeç</button><button type="submit" class="btn btn-danger"><i class="bi bi-arrow-counterclockwise"></i>Onayla</button></div>
  </form>
</div>
