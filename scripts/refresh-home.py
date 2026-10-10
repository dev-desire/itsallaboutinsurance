#!/usr/bin/env python3
"""Apply the screenshot-inspired homepage using native editable Elementor widgets."""
import json, hashlib, shutil
from pathlib import Path
R=Path(__file__).resolve().parents[1]
P=R/'wp-content/plugins/iaai-portal'
# The committed transparent brand asset is reused when regenerating layouts.
n=0
def item(kind,settings,children=None):
 global n
 n+=1
 return dict(id=f'a{n:06x}',elType='container' if kind=='container' else 'widget',**({} if kind=='container' else {'widgetType':kind}),settings=settings,elements=children or [])
def h(t,size=38):return item('heading',{'title':t,'header_size':'h2','title_color':'#24133f','typography_typography':'custom','typography_font_size':{'size':size,'unit':'px'},'typography_font_size_mobile':{'size':min(size,32),'unit':'px'}})
def t(s):return item('text-editor',{'editor':s,'text_color':'#514567'})
def b(s,u):return item('button',{'text':s,'link':{'url':u},'background_color':'#7c3aed','button_text_color':'#ffffff','border_radius':dict(top=12,right=12,bottom=12,left=12,unit='px')})
def box(c,bg='#ffffff',row=False,cls=''):
 if row:
  for child in c:
   child['settings'].update(width={'size':(100/len(c))-2,'unit':'%'},width_mobile={'size':100,'unit':'%'},flex_grow=1)
 return item('container',{'content_width':'full','flex_direction':'row' if row else 'column','flex_direction_mobile':'column','flex_wrap':'wrap','gap':{'size':24,'unit':'px'},'padding':dict(top=30,right=30,bottom=30,left=30,unit='px'),'background_background':'classic','background_color':bg,'border_radius':dict(top=24,right=24,bottom=24,left=24,unit='px'),'css_classes':cls},c)
def section(c,bg='#ffffff'):
 return item('container',{'content_width':'boxed','boxed_width':{'size':1200,'unit':'px'},'padding':dict(top=65,right=24,bottom=65,left=24,unit='px'),'background_background':'classic','background_color':bg,'css_classes':'iaai-home-section'},c)
for lang in ['en','hi']:
 L=lambda en,hi:hi if lang=='hi' else en
 u=lambda s:('/hi/' if lang=='hi' else '/')+s+'/'
 hero=section([box([box([t(L('YOUR NEXT CHAPTER, PROTECTED','आपका अगला कदम, सुरक्षित')),h(L('Insurance made simple.<br>Confidence made personal.','बीमा की आसान समझ।<br>आपकी जरूरत का मार्गदर्शन।'),56),t(L('Explore health, term, policy reviews and claim guidance with Anand Ahirwar’s team.','आनंद अहिरवार की टीम के साथ स्वास्थ्य, टर्म, पॉलिसी समीक्षा और क्लेम सहायता समझें।')),box([t(L('✓ Family protection','✓ परिवार की सुरक्षा')),t(L('✓ Clear policy terms','✓ स्पष्ट पॉलिसी शर्तें'))],'#fff0e5',True),b(L('Explore your options →','विकल्प जानें →'),'#services')],'#faf7ff'),box([h(L('What would you like to protect?','किसकी सुरक्षा समझना चाहते हैं?'),28),t(L('Choose a starting point. No personal information is needed to explore.','शुरुआत चुनें। जानने के लिए निजी जानकारी की जरूरत नहीं।')),b(L('Health & family →','स्वास्थ्य और परिवार →'),u('health-insurance')),b(L('Term & dependants →','टर्म और आश्रित →'),u('term-insurance')),b(L('Review my policy →','पॉलिसी की समीक्षा →'),u('policy-review')),b(L('Help with a claim →','क्लेम में सहायता →'),u('claim-help'))],'#e8e7ff')],'#faf7ff',True)],'#faf7ff')
 hero['elements'][0]['elements'][0]['elements'][1]['settings']['header_size']='h1'
 route=section([h(L('One place. Four clear routes.','एक जगह। चार स्पष्ट रास्ते।')),box([box([h(icon,38),h(L(en,hi),23),t(L(desc,dh)),b(L('Explore →','जानें →'),u(slug))],'#ffffff',cls='iaai-color-card') for icon,en,hi,desc,dh,slug in [('♡','Health','स्वास्थ्य','For individuals, families and parents.','व्यक्ति, परिवार और माता-पिता के लिए।','health-insurance'),('◈','Term','टर्म','Plan for the people who depend on you.','आप पर निर्भर लोगों के लिए योजना।','term-insurance'),('✓','Policy review','पॉलिसी समीक्षा','Understand waiting periods and exclusions.','वेटिंग पीरियड और exclusions समझें।','policy-review'),('↗','Claim guidance','क्लेम मार्गदर्शन','Organise your questions and next steps.','सवाल और अगला कदम व्यवस्थित करें।','claim-help')]],row=True)])
 route['settings']['css_id']='services'
 special=section([h(L('What makes clarity special?','स्पष्ट जानकारी क्यों जरूरी है?')),t(L('A human conversation, practical checklists and a clear next step.','मानवीय बातचीत, उपयोगी चेकलिस्ट और स्पष्ट अगला कदम।')),box([box([h(num,56),h(L(en,hi),26),t(L(desc,dh))],bg,cls='iaai-color-card') for num,en,hi,desc,dh,bg in [('01','People first','आपकी जरूरत पहले','Start with your family and responsibilities.','परिवार और जिम्मेदारियों से शुरुआत करें।','#ffe28a'),('02','Plain language','आसान भाषा','Break policy jargon into useful questions.','पॉलिसी की भाषा को उपयोगी सवालों में समझें।','#b8ddff'),('03','A clear next step','स्पष्ट अगला कदम','Know what to prepare before a conversation.','बातचीत से पहले जरूरी तैयारी जानें।','#ffbdad')]],row=True)])
 demo=section([h(L('Explore a sample journey','एक उदाहरण देखें')),t(L('Illustrative scenarios — not customer testimonials.','उदाहरण के लिए परिस्थितियां — ग्राहक प्रशंसापत्र नहीं।')),box([box([h(L(a,bh),24),t(L(c,d))],'#f3edff',cls='iaai-color-card') for a,bh,c,d in [('A young family','एक युवा परिवार','Sample: compare family cover, waiting periods and co-pay.','उदाहरण: परिवार का कवर, वेटिंग पीरियड और को-पे की तुलना करें।'),('Growing responsibilities','बढ़ती जिम्मेदारियां','Sample: estimate protection needs before discussing term insurance.','उदाहरण: टर्म बीमा की चर्चा से पहले सुरक्षा की जरूरत का अनुमान।'),('An existing policy','एक मौजूदा पॉलिसी','Sample: gather your schedule and questions for a policy review.','उदाहरण: समीक्षा के लिए पॉलिसी शेड्यूल और सवाल तैयार करें।')]],row=True)])
 calculators=section([h(L('Calculate a clearer starting point','गणना से बेहतर शुरुआत')),t(L('Planning estimates run on your device. They are not insurer quotes or guaranteed returns.','योजना के अनुमान आपके डिवाइस पर बनते हैं। ये बीमा कंपनी के कोट या सुनिश्चित रिटर्न नहीं हैं।')),item('iaai-calculators',{})],'#faf7ff')
 career=section([box([box([t(L('GROW WITH PURPOSE','उद्देश्य के साथ आगे बढ़ें')),h(L('Interested in working with us?','हमारे साथ काम करना चाहते हैं?'),44),t(L('Start a conversation about supporting insurance education and customer guidance. Opportunities depend on the team’s current requirements.','बीमा शिक्षा और ग्राहक मार्गदर्शन में सहयोग की चर्चा करें। अवसर टीम की वर्तमान जरूरतों पर निर्भर हैं।')),t(L('✓ Meaningful conversations<br>✓ Learning and collaboration<br>✓ Clear expectations','✓ उपयोगी बातचीत<br>✓ सीखना और सहयोग<br>✓ स्पष्ट अपेक्षाएं')),b(L('Discuss opportunities →','अवसरों पर चर्चा करें →'),u('contact'))],'#ffe28a'),box([h(L('Your strengths. A useful conversation.','आपकी खूबियां। उपयोगी बातचीत।'),28),t(L('Share your interests and experience with the team. No earnings or placement promises.','टीम के साथ रुचि और अनुभव साझा करें। आय या नियुक्ति का वादा नहीं।')),b(L('WhatsApp the team ↗','टीम से WhatsApp पर बात करें ↗'),'https://wa.me/919179826317')],'#fff8de')],'#ffe28a',True)],'#fff8de')
 path=R/f'elementor/pages/home-{lang}.json';data=json.loads(path.read_text());data['content']=[hero,route,special,demo,calculators,career];path.write_text(json.dumps(data,ensure_ascii=False,indent=2))
 header=R/f'elementor/global/header-{lang}.json';data=json.loads(header.read_text());data['content'][0]['elements'][0]['elements']=[item('image',{'image':{'url':'https://raw.githubusercontent.com/dev-desire/itsallaboutinsurance/main/wp-content/plugins/iaai-portal/assets/logo-purple.png'},'image_size':'full','width':{'size':250,'unit':'px'},'width_mobile':{'size':210,'unit':'px'},'link_to':'custom','link':{'url':'/hi/' if lang=='hi' else '/'},'caption_source':'none'})];header.write_text(json.dumps(data,ensure_ascii=False,indent=2))
m=R/'elementor/manifest.json';data=json.loads(m.read_text())
for entry in data['templates']:
 if entry['key'] in ['home','header']:
  document=json.loads((R/'elementor'/entry['file']).read_text());entry['hash']=hashlib.sha256(json.dumps(document,ensure_ascii=False,sort_keys=True).encode()).hexdigest()
m.write_text(json.dumps(data,ensure_ascii=False,indent=2))
