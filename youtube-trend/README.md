# YouTube Trend Analizi

İngilizce bir YouTube kanalı için **neyin tuttuğunu veriyle bulan** araç. YouTube Data API'den gerçek
rakamları çeker, nişleri karşılaştırır, küçük kanalların patlayan videolarını bulur ve isteğe bağlı olarak
Claude ile bu veriye dayalı bir içerik planı çıkarır.

## Ne cevaplıyor?

| Soru | Komut |
|---|---|
| Şu an ABD'de (veya başka bir ülkede) ne trend? | `trending` |
| "kids bedtime stories" nişi ne kadar talep görüyor, yeni kanal girebilir mi? | `niche` |
| 8 farklı nişten hangisi en fırsatlı? | `scan` |
| Hangi videolar şu an gerçekten hızlanıyor? | `growth` (tekrarlı taramalardan) |
| Bu veriye göre hangi videoları çekmeliyim? | herhangi bir komuta `--ai` |

## Kurulum

```bash
cd youtube
python -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env   # anahtarları doldurun
```

**YouTube API anahtarı (ücretsiz):**
1. https://console.cloud.google.com adresinde bir proje oluşturun.
2. "APIs & Services > Library" bölümünden **YouTube Data API v3**'ü etkinleştirin.
3. "Credentials > Create credentials > API key" ile anahtar alın ve `.env` dosyasında `YOUTUBE_API_KEY` alanına yazın.

`ANTHROPIC_API_KEY` yalnızca `--ai` seçeneği için gerekir.

## Kullanım

```bash
# Ülke trendleri (Film & Animation kategorisi)
python -m yttrend trending --region US --category 1

# Tek niş, son 30 gün, sadece Shorts
python -m yttrend niche "kids bedtime story" --days 30 --duration short

# config/nisler.json içindeki tüm nişleri karşılaştır ve Claude'dan plan iste
python -m yttrend scan --ai

# Aynı taramayı farklı günlerde tekrarlayınca gerçek büyüme hızını gösterir
python -m yttrend growth
```

Her çalıştırma `output/` klasörüne üç dosya yazar: `.md` (okunacak rapor), `.csv` (Excel/Sheets için) ve
`.json` (ham veri). Tüm ölçümler ayrıca `data/trend.db` (SQLite) dosyasında birikir.

Nişleri `config/nisler.json` dosyasından düzenleyebilirsiniz. Her nişte `name` ve `query` zorunlu, `duration` ise
isteğe bağlıdır (`short`, `medium` veya `long`).

## Metrikler

- **Günlük izlenme:** izlenme / video yaşı. Eski ve yeni videoları adil karşılaştırır.
- **Outlier (izlenme / abone):** 1'den büyükse video kanalın kendi kitlesinin dışına taşmış, yani algoritma
  onu öne çıkarmış demektir. **Küçük kanalda yüksek outlier, kopyalanabilir bir format sinyalidir.**
- **Küçük kanal payı:** listeye giren videoların ne kadarı 50 binden az aboneli kanallardan geliyor. Düşükse niş
  büyük kanallara kilitlenmiştir.
- **Fırsat skoru:** `log10(medyan günlük izlenme) × (0,5 + küçük kanal payı)`. Talep yüksekse ve küçük kanallar
  da kazanabiliyorsa skor yükselir.
- **Başlık kalıpları:** sık geçen kelimeler ve etiketler; rakam, soru, BÜYÜK HARF ve emoji kullanım oranları.

## API kotası

Günlük ücretsiz kota 10.000 birim. Komutların yaklaşık maliyeti:

| Komut | Maliyet |
|---|---|
| `trending` | ~3 birim |
| `niche` | ~102 birim (arama 100 + detaylar) |
| `scan` (8 niş) | ~820 birim |

Yani günde rahatça 10 tam tarama yapılabilir. Her rapor harcanan kotayı gösterir.

## Önerilen rutin

1. **1. hafta:** Her gün `scan` çalıştırın. `growth` gerçek ivmeyi göstermeye başlar.
2. En yüksek fırsat skoruna sahip 1–2 niş için `niche ... --ai` ile 10 video fikri çıkarın.
3. Küçük kanalların outlier videolarını tek tek izleyin. Kopyalanacak şey video değil, **format**:
   uzunluk, açılış, karakter, başlık yapısı.
4. Kanalı açtıktan sonra kendi videolarınızı da aynı metriklerle takip edin.

## Testler

```bash
python -m pytest -q
```

Testler gerçek API'ye bağlanmaz, sahte yanıtlarla çalışır.
