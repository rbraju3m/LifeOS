# 2. Business Requirement Document (BRD)

- **Business objective:** become the default personal and family life organiser in Bangladesh, then expand to the South Asian diaspora (UAE, KSA, Malaysia, UK, USA), then globally.
- **Model:** free-first freemium. The free tier must be genuinely useful. Revenue comes later from Personal Plus (৳99/mo), Family (৳199/mo) and Family Pro (৳399/mo). **All prices are hypotheses** and live in DB configuration (see §17).
- **Principles (binding):** users own their data. Export and deletion are always free. Downgrading never locks or deletes existing data; it only blocks creating new data above the limit.
- **Cost constraint:** infrastructure under about US$60/month until there are around 10k MAU (one VPS, managed object storage, transactional email on a free or low tier).
- **Payment collection (future):** Bangladeshi gateways (SSLCommerz, bKash, Nagad) plus Stripe/Paddle for international users, behind a `BillingProvider` interface. Out of scope for the MVP.
- **Compliance:** privacy policy and ToS in bn/en, a consent record at signup, collect as little data as possible (name, email, optional phone; no DOB, gender or NID number at signup), and track Bangladesh data-protection legislation (Q3). GDPR-ready design (export, erasure, purpose limitation) supports global expansion.
- **Key risks:** low willingness to pay in Bangladesh (mitigation: the family plan is the main value lever), reminder reliability damaging trust, sensitive-data breach, trademark (Q9).
