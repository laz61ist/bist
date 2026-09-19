<?php $searchUrl = ($area ?? 'manager') === 'manager' && app()->router()->hasRoute('search') ? route('search') : ''; ?>
<div class="cmdk" x-show="cmdkOpen" x-cloak @keydown.escape.window="cmdkOpen=false">
  <div class="overlay" @click="cmdkOpen=false"></div>
  <div class="cmdk-box" x-data="cmdk('<?= e($searchUrl) ?>')" x-init="$watch('cmdkOpen', v => { if (v) { $nextTick(() => $refs.q.focus()); q=''; results=[]; sel=0; } })" style="position:relative" x-effect="cmdkOpen && $nextTick(() => $refs.q && $refs.q.focus())">
    <div class="cmdk-input"><i class="bi bi-search text-muted"></i><input x-ref="q" x-model="q" @input="search()" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter.prevent="go()" placeholder="Sayfa, bağımsız bölüm, kişi, makbuz no ara…" autocomplete="off"><span class="text-muted small" x-show="loading">aranıyor…</span><kbd @click="cmdkOpen=false">Esc</kbd></div>
    <div class="cmdk-list">
      <template x-for="(item, i) in filtered()" :key="item.u + i">
        <div>
          <div class="grp" x-show="i === 0 || filtered()[i-1].g !== item.g" x-text="item.g"></div>
          <a :href="item.u" :class="{'is-sel': i === sel}" @mouseenter="sel = i"><i class="bi" :class="'bi-' + item.i"></i><span x-text="item.t"></span><span class="k" x-text="item.k"></span></a>
        </div>
      </template>
      <div class="empty" x-show="filtered().length === 0" style="padding:24px"><p>Sonuç yok.</p></div>
    </div>
    <div class="cmdk-foot"><span><kbd>↑</kbd><kbd>↓</kbd> gez</span><span><kbd>↵</kbd> aç</span><span><kbd>g</kbd> + harf: hızlı git</span></div>
  </div>
</div>
<div class="modal" x-data="{open:false}" x-show="open" x-cloak @open-shortcuts.window="open=true" @keydown.escape.window="open=false">
  <div class="overlay" @click="open=false"></div>
  <div class="modal-box" style="position:relative">
    <div class="modal-head"><h3>Klavye kısayolları</h3><button class="icon-btn" @click="open=false"><i class="bi bi-x-lg"></i></button></div>
    <div class="modal-body">
      <dl class="dl">
        <dt><kbd>⌘</kbd>/<kbd>Ctrl</kbd> + <kbd>K</kbd></dt><dd>Ara / komut paleti</dd>
        <dt><kbd>g</kbd> <kbd>d</kbd></dt><dd>Genel bakış</dd>
        <dt><kbd>g</kbd> <kbd>b</kbd></dt><dd>Bağımsız bölümler</dd>
        <dt><kbd>g</kbd> <kbd>k</kbd></dt><dd>Kişiler</dd>
        <dt><kbd>g</kbd> <kbd>a</kbd></dt><dd>Borç kayıtları</dd>
        <dt><kbd>g</kbd> <kbd>t</kbd></dt><dd>Tahsilatlar</dd>
        <dt><kbd>n</kbd> <kbd>t</kbd></dt><dd>Yeni tahsilat</dd>
        <dt><kbd>g</kbd> <kbd>g</kbd></dt><dd>Giderler</dd>
        <dt><kbd>g</kbd> <kbd>r</kbd></dt><dd>Raporlar</dd>
        <dt><kbd>?</kbd></dt><dd>Bu pencere</dd>
      </dl>
    </div>
  </div>
</div>
