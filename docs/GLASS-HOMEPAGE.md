# Glass homepage and daily publishing

This release prepares a purple glassmorphism front end for the existing WordPress site, not a new application. Deploy it through the existing checked release workflow; GitHub push alone does not prove installation when DEPLOY_ENABLED is off. Preserve manual Elementor edits using the template conflict checks.

## Included

- English/Hindi homepage: photo hero, four service cards, existing calculators near the top, colourful hover cards, explicitly labelled sample journeys, insurance learning, daily blog preview and career section last.
- `/blog/` and `/hi/blog/`: published WordPress Post feed with paging.
- `/insurance-guides/` and `/hi/insurance-guides/`: eight-step general orientation with photos, anchor navigation and official resource links.
- Existing `/learn/` hub points to guides and the journal. Header displays Services, Guides, Blog, About and Contact, with existing language/account controls.
- Native Elementor heading, text, image, button and container widgets retain content and responsive design controls. `IAAI Blog Posts` is a small dynamic widget with count/category, pagination, labels, colours, typography, columns and spacing controls. `IAAI Calculators` retains label/style controls and formulas.
- Single blog template supports native WordPress content and Elementor article content. No dummy WordPress posts, fake premiums or customer testimonials are inserted.

## Daily blog workflow

1. WordPress **Posts → Add New**: title, article, featured image, excerpt and category; publish when ready.
2. Use **Edit with Elementor** for the article body. If missing, enable Posts under Elementor Settings → General → Post Types. Page hero/feed design is separately editable on the Blog page.
3. The feed automatically reads published posts, newest first; drafts, private and password-protected posts are excluded.
4. For separate Hindi/English lists, create language categories and set the category ID in each page's Blog Posts widget. Default 0 shows all published posts; no automatic translation is implied.
5. Sample homepage reading cards and photo scenarios are native editable widgets. Replace or remove them when production content is ready; they link to the actual guide steps, not fabricated articles.

## Build and deployment

Canonical layout changes live in `scripts/refresh-home.py`; run `python3 scripts/refresh-home.py` and `python3 scripts/build.py`. If rebuilding baseline templates with `generate-templates.py`, run refresh-home afterwards. The manifest now includes 57 templates (53 pages plus four globals). Existing specialist guide drafts and their source/author/reviewer publication checks remain intact.

Bundled photo URLs and logo use the current production plugin assets path. If relocating the domain or plugin directory, update `ASSET` in refresh-home.py before regenerating. No external stock-photo HTTP request is needed in visitor browsers.

## Content and image provenance

Illustrative stock photos from Unsplash, retrieved 11 October 2026 (cropped JPEGs are bundled, not testimonials):
- family.jpg: https://images.unsplash.com/photo-1511895426328-dc8714191300
- care.jpg: https://images.unsplash.com/photo-1576091160399-112ba8d25d1d
- planning.jpg: https://images.unsplash.com/photo-1450101499163-c8848c66ca85
- wellbeing.jpg: https://images.unsplash.com/photo-1506126613408-eca07ce68773

The learning page is a general orientation, not plan-specific advice. It avoids fixed regulatory deadlines/benefits and points visitors to their actual documents. Reference checks: IRDAI policyholder portal https://policyholder.gov.in/; IRDAI document detail https://irdai.gov.in/document-detail?documentId=376507 (proposal disclosures/policyholder protection); IRDAI Master Circular on Life Insurance Products 12 June 2024, reference IRDAI/ACTL/MSTCIR/MISC/89/6/2024 (CIS, benefits, exclusions, claim/support information). Verify current plan-specific rules before expanding specialist articles.

Calculators retain the prior formulas and their stated scope. Tax is explicitly FY 2025–26/AY 2026–27, not relabelled as a current-year calculator without a separate rule review.
