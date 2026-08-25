# Akademik tarama — yöntem ve kısıt bildirimi

> **Bu belgeyi okumadan taramanın bulgularını kullanmayın.**

## Kısa hüküm

**Tam metni okunan hakemli kaynak sayısı: 0.**
Dört bağımsız tarama ajanı çalıştırıldı, dördü de aynı sonuca ulaştı.
Bu bir literatür taraması **değildir**; doğrulanmamış bir **araştırma haritasıdır**.

## Neden

Bu oturumun ağ çıkışı, kaynak evrenindeki alan adlarının tamamını
CONNECT seviyesinde reddediyor. Hem `WebFetch` (`EGRESS_BLOCKED`) hem
bağımsız `curl` (`CONNECT tunnel failed, response 403`) aynı sonucu verdi.

| Alan adı | Kaynak evreninde mi | Sonuç |
|---|---|---|
| `arxiv.org` | evet | 403 — engelli |
| `scholar.google.com` | evet | 403 — engelli |
| `ieeexplore.ieee.org` | evet | 403 — engelli |
| `www.sciencedirect.com` | evet | 403 — engelli |
| `link.springer.com` | evet | 403 — engelli |
| `www.emerald.com` | evet | 403 — engelli |
| `www.mdpi.com` | evet | 403 — engelli |
| `doi.org`, `api.crossref.org` | hayır | 403 — DOI doğrulaması da yapılamadı |
| `dergipark.org.tr` | hayır | 403 — engelli |
| `github.com` | hayır | **erişilebilir** (kontrol testi) |

Kaynak evreninin **7 üyesinin 7'si** de kapalı. Engel seçici değil.
`github.com` kontrol testinin geçmesi, bunun geçici bir arıza değil
bilinçli bir politika kısıtı olduğunu gösteriyor.

`/root/.ccr/README.md` proxy 403'leri için açıkça *"Do not retry or route
around it — report the blocked host"* diyor. Dolanılmadı.

Yalnızca `WebSearch` çalışıyor; bu araç sunucu tarafında sentezlenmiş
**özet** döndürüyor — abstract bile değil.

## Bunun sonuçları

1. **Hiçbir iddia birincil kaynaktan doğrulanmadı.** Aktarılan her şey
   arama motorunun ikincil özetinden geliyor.
2. **Hiçbir DOI doğrulanmadı.** Crossref de kapalı.
3. **Yazar adları ve yıllar büyük ölçüde doğrulanamadı**; doğrulanamayanlar
   `[DOĞRULANMASI GEREKİYOR]` ile işaretli.
4. **Mevzuat madde numarası verilmedi.** Görülen referanslar uydurulmadı,
   aktarıldı ve doğrulanmamış olarak işaretlendi.
5. **Çelişkiler çözülemedi** — tam metin olmadan uzlaştırılamaz.
6. **Prompt injection denetimi anlamlı yapılamadı**: hiçbir tam metin
   çekilemediği için `[INJECTION ŞÜPHESİ]` bulgusunun olmaması
   **zayıf bir negatiftir**, güvence değildir.

Bu, `skill-anayasa` §5.2'nin "sayı şişirilmez, gerçek sayı ve neden
raporlanır" hükmünün uygulanmasıdır. Dört ajan da sayıyı 0 olarak
raporladı; hiçbiri özet okumayı tam okuma diye saymadı.

## Erişim açılırsa ne yapılmalı

Şu alan adları oturumun egress allowlist'ine eklenirse tarama tam
okumayla yeniden üretilebilir:

```
arxiv.org  scholar.google.com  ieeexplore.ieee.org
www.sciencedirect.com  link.springer.com  www.emerald.com
www.mdpi.com  doi.org  api.crossref.org  dergipark.org.tr
```

Öncelikli okuma listesi `01-bulgular-ve-gereksinimler.md` sonundadır.

## Bu haritanın yine de değeri

Aday kaynakları isabetli biçimde belirliyor ve **yazılım gereksinimleri
türetiyor**. Gereksinimler, kaynak künyelerinin doğruluğundan bağımsız
olarak test edilebilir tasarım kararlarıdır — nitekim bir kısmı bu
depoda uygulandı ve testle sabitlendi. Atıf verilebilir literatür özeti
olarak **kullanılmamalıdır**.
