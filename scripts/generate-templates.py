#!/usr/bin/env python3
"""Generate separate native Elementor layouts and a stable deployment manifest."""
from pathlib import Path
import json, hashlib, html
ROOT=Path(__file__).resolve().parents[1]
counter=0
def uid():
 global counter
 counter+=1
 return f'{counter:07x}'
def widget(kind,settings):
 return {'id':uid(),'elType':'widget','widgetType':kind,'settings':settings,'elements':[]}
def heading(text,size=32,cls='',tag='h2',color='#102a43'):
 return widget('heading',{'title':text,'header_size':tag,'title_color':color,'typography_typography':'custom','typography_font_family':'Inter','typography_font_size':{'unit':'px','size':size},'_css_classes':cls})
def text(body,color='#61727d',size=16):
 return widget('text-editor',{'editor':body,'text_color':color,'typography_typography':'custom','typography_font_family':'Inter','typography_font_size':{'size':size,'unit':'px'}})
def button(label,url,secondary=False):
 return widget('button',{'text':label,'link':{'url':url},'background_color':'#ffffff' if secondary else '#0f766e','button_text_color':'#102a43' if secondary else '#ffffff','border_border':'solid','border_width':{'top':1,'right':1,'bottom':1,'left':1,'unit':'px'},'border_color':'#dce6e3' if secondary else '#0f766e','border_radius':{'top':8,'right':8,'bottom':8,'left':8,'unit':'px'},'text_padding':{'top':16,'right':23,'bottom':16,'left':23,'unit':'px'},'typography_font_size':{'size':14,'unit':'px'}})
def box(children,direction='column',padding=0,bg=None,cls='',width=None,**extras):
 settings={'content_width':'full','flex_direction':direction,'flex_direction_mobile':'column','gap':{'size':18,'unit':'px'},'padding':{'top':padding,'right':padding,'bottom':padding,'left':padding,'unit':'px'},'_css_classes':cls}
 if direction=='row':settings['flex_align_items']='center'
 if bg:settings.update(background_background='classic',background_color=bg)
 if width:settings.update(width={'size':width,'unit':'%'},width_mobile={'size':100,'unit':'%'})
 settings.update(extras)
 return {'id':uid(),'elType':'container','settings':settings,'elements':children,'isInner':True}
def section(children,bg='#fff',padding=75):
 inner=box(children,content_width='boxed',boxed_width={'size':1200,'unit':'px'},padding=0)
 inner['settings']['padding']={'top':0,'right':24,'bottom':0,'left':24,'unit':'px'}
 return box([inner],padding=padding,bg=bg,padding_mobile={'top':42,'right':0,'bottom':42,'left':0,'unit':'px'})
def label(en,hi,lang):return hi if lang=='hi' else en
def url(slug,lang):return ('/hi/' if lang=='hi' else '/')+slug.strip('/')+'/' if slug else ('/hi/' if lang=='hi' else '/')
def card(title,body,symbol,target,lang):
 return box([heading(symbol,28,tag='span',color='#0f766e'),heading(title,23,tag='h3'),text(body,size=14),button(label('Explore →','जानें →',lang),target,True)],padding=28,bg='#fff',cls='iaai-card-hover',width=25,border_border='solid',border_width={'top':1,'right':1,'bottom':1,'left':1,'unit':'px'},border_color='#dde5e7',border_radius={'top':16,'right':16,'bottom':16,'left':16,'unit':'px'})
manifest={'version':'0.1.0','templates':[]}
def save(key,lang,title,slug,content,description='',private=False,draft=False,parent=None,glob=False):
 folder='global' if glob else 'pages'
 target=ROOT/'elementor'/folder/(key+'-'+lang+'.json')
 data={'version':'0.4','title':title,'type':'section' if glob else 'page','content':content,'page_settings':{'hide_title':'yes'}}
 target.write_text(json.dumps(data,ensure_ascii=False,indent=2))
 item={'key':key,'locale':lang,'slug':slug,'file':folder+'/'+target.name,'hash':hashlib.sha256(json.dumps(data,ensure_ascii=False,sort_keys=True).encode()).hexdigest(),'description':description,'private':private,'draft':draft,'global':glob}
 if parent:item['parent']=parent
 manifest['templates'].append(item)
services=[
 ('health','health-insurance','Health insurance','स्वास्थ्य बीमा','For you, your family and your parents. Start with the right questions.','अपने और परिवार के लिए सही सवालों से शुरुआत करें।','○'),
 ('term','term-insurance','Term insurance','टर्म बीमा','Understand protection for the people who depend on you.','आप पर निर्भर लोगों की सुरक्षा को समझें।','↗'),
 ('review','policy-review','Policy review','पॉलिसी समीक्षा','Make sense of the terms in a policy you already have.','मौजूदा पॉलिसी की शर्तों को समझें।','✓'),
 ('claim','claim-help','Claim help','क्लेम सहायता','Explain your issue and get a clear next-step process.','अपनी समस्या बताएं और अगली प्रक्रिया समझें।','→')]
for lang in ['en','hi']:
 L=lambda en,hi:label(en,hi,lang)
 save('header',lang,'Header','header',[box([
  box([heading("It's All About<br>Insurance",19,tag='div'),text(L('UNDERSTAND. CHOOSE. FEEL PREPARED.','समझें। चुनें। तैयार रहें।'),size=9)],width=25),
  text('<nav aria-label="Main"><a href="'+url('health-insurance',lang)+'">'+L('Services','सेवाएं')+'</a> &nbsp; <a href="'+url('learn',lang)+'">'+L('Learn','जानें')+'</a> &nbsp; <a href="'+url('stories',lang)+'">'+L('Stories','अनुभव')+'</a> &nbsp; <a href="'+url('about',lang)+'">'+L('About','परिचय')+'</a> &nbsp; <a href="'+url('contact',lang)+'">'+L('Contact','संपर्क')+'</a></nav>',size=13),
  widget('iaai-navigation',{})],direction='row',padding=20,content_width='boxed',boxed_width={'size':1240,'unit':'px'},flex_justify_content='space-between')],glob=True)
 save('footer',lang,'Footer','footer',[section([
  box([box([heading("It's All About Insurance",25,color='#fff'),text(L('Understand your insurance.<br>Make informed choices.','बीमा को समझें।<br>फैसले बेहतर लें।'),'#c4d6df',16)],width=55),
   box([heading(L('Stay connected','जुड़े रहें'),15,tag='h3',color='#fff'),text('<a href="https://www.instagram.com/its_all_about_insurance_/" rel="noopener" target="_blank">Instagram ↗</a><br><a href="'+url('contact',lang)+'">'+L('Get guidance','मार्गदर्शन लें')+'</a>','#c4d6df',14)],width=45)],direction='row'),
  text('<a href="'+url('privacy',lang)+'">'+L('Privacy','गोपनीयता')+'</a> &nbsp; <a href="'+url('terms',lang)+'">'+L('Terms','शर्तें')+'</a> &nbsp; <a href="'+url('disclosures',lang)+'">'+L('Disclosures','डिस्क्लोज़र')+'</a><br>'+L('Guidance is subject to the actual service scope. Claim decisions remain with the insurer.','मार्गदर्शन वास्तविक सेवा के दायरे के अनुसार है। क्लेम का निर्णय बीमा कंपनी करती है।'),'#c4d6df',12)
 ],'#102a43',55)],glob=True)
 hero=section([box([
  box([heading(L('INSURANCE, IN PLAIN LANGUAGE','बीमा, आसान भाषा में'),11,'iaai-section-label',tag='div',color='#0f766e'),
   heading(L('Understand your insurance.<br><span style="color:#0f766e">Make informed choices.</span>','बीमा को समझें।<br><span style="color:#0f766e">फैसले बेहतर लें।</span>'),64,'iaai-hero-title',tag='h1'),
   text(L('Guidance on health insurance, term insurance, policy reviews and claim-related questions from Anand Ahirwar’s team.','Anand Ahirwar की टीम से स्वास्थ्य बीमा, टर्म बीमा, पॉलिसी समीक्षा और क्लेम से जुड़े सवालों पर मार्गदर्शन।'),size=18),
   box([button(L('Get guidance ↗','मार्गदर्शन लें ↗'),url('contact',lang)),button(L('Claim help','क्लेम सहायता'),url('claim-help',lang),True)],direction='row'),
   text(L('A clear starting point. A conversation around your needs.','स्पष्ट शुरुआत। आपकी जरूरतों पर बातचीत।'),size=12)],width=58),
  box([
   heading(L('ONE CLEAR NEXT STEP','एक स्पष्ट अगला कदम'),11,'iaai-section-label',tag='div',color='#0f766e'),
   box([heading(L('Your insurance.<br>Better understood.','आपका बीमा।<br>बेहतर समझ।'),28),text(L('Choose the route that fits your question.','अपने सवाल के अनुसार रास्ता चुनें।'),size=14),
    text('01 &nbsp; '+L('Buying for your family?','परिवार के लिए बीमा लेना है?')+'<br>02 &nbsp; '+L('Understanding an existing policy?','मौजूदा पॉलिसी समझनी है?')+'<br>03 &nbsp; '+L('Need help with a claim?','क्लेम में सहायता चाहिए?'),color='#102a43',size=14),
    button(L('Find your route →','अपना रास्ता चुनें →'),'#services',True)],padding=28,bg='#fff',cls='iaai-screen-card',border_radius={'top':15,'right':15,'bottom':15,'left':15,'unit':'px'}),
   box([heading('✓',22,tag='span',color='#0f766e'),text(L('Clear explanations.<br>No universal “best policy” promises.','स्पष्ट जानकारी।<br>हर व्यक्ति के लिए एक “best policy” का वादा नहीं।'),size=13)],direction='row',padding=15,bg='#fff',border_radius={'top':12,'right':12,'bottom':12,'left':12,'unit':'px'})
  ],padding=28,bg='#eaf5f0',cls='iaai-hero-orbit',width=42,border_radius={'top':28,'right':28,'bottom':28,'left':28,'unit':'px'})
 ],direction='row',gap={'size':55,'unit':'px'})],'#f8faf8',85)
 serviceblock=section([heading(L('HOW CAN WE HELP?','कैसी सहायता चाहिए?'),11,'iaai-section-label',tag='div',color='#0f766e'),heading(L('Start with what matters to you.','अपनी जरूरत से शुरुआत करें।'),40,'iaai-section-title'),
  box([card(s[2] if lang=='en' else s[3],s[4] if lang=='en' else s[5],s[6],url(s[1],lang),lang) for s in services],direction='row',flex_align_items='stretch',gap={'size':16,'unit':'px'})])
 serviceblock['settings']['css_id']='services'
 process=section([box([box([heading(L('A SIMPLE PROCESS','आसान प्रक्रिया'),11,'iaai-section-label',tag='div',color='#0f766e'),heading(L('A little clarity.<br>A lot more confidence.','थोड़ी स्पष्टता।<br>बेहतर समझ।'),40,'iaai-section-title'),text(L('You share the question. The team reviews the enquiry and helps you understand the next step.','आप सवाल बताएं। टीम enquiry की समीक्षा करके अगला कदम समझाने में मदद करेगी।'))],width=45),
  box([box([heading(n,16,tag='span',color='#0f766e'),box([heading(L(a,b),21,tag='h3'),text(L(c,d),size=14)])],direction='row',padding=18,bg='#fff',border_radius={'top':12,'right':12,'bottom':12,'left':12,'unit':'px'}) for n,a,b,c,d in [
  ('01','Share your need','अपनी जरूरत बताएं','Choose Health, Term, Review or Claim Help.','स्वास्थ्य, टर्म, समीक्षा या क्लेम सहायता चुनें।'),
  ('02','Team reviews your enquiry','टीम enquiry की समीक्षा करेगी','Keep a reference and clear next steps.','Reference और स्पष्ट अगला कदम मिलेगा।'),
  ('03','Understand your options','विकल्पों को समझें','Discuss the terms and questions relevant to you.','आपके लिए जरूरी शर्तों और सवालों पर चर्चा करें।')]],width=55)],direction='row')],'#f0f6f3',70)
 learn=section([heading(L('LESS CONFUSION. MORE CLARITY.','कम उलझन। अधिक स्पष्टता।'),11,'iaai-section-label',tag='div',color='#0f766e'),heading(L('Good questions make a better start.','सही सवाल बेहतर शुरुआत हैं।'),40,'iaai-section-title'),
 box([card(L('For your family','आपके परिवार के लिए'),L('Prepare a checklist before a health-insurance conversation.','स्वास्थ्य बीमा की बातचीत से पहले checklist तैयार करें।'),'01',url('learn',lang),lang),
 card(L('For an existing policy','मौजूदा पॉलिसी के लिए'),L('Know which policy terms to ask about during a review.','समीक्षा में पूछने वाली शर्तों को जानें।'),'02',url('learn',lang),lang),
 card(L('For claim questions','क्लेम से जुड़े सवाल'),L('Organize the issue and documents for a human review.','समस्या और दस्तावेज़ व्यवस्थित करें।'),'03',url('learn',lang),lang)],direction='row',flex_align_items='stretch')])
 founder=section([box([box([heading('AA',74,tag='span',color='#0f766e'),text(L('Anand Ahirwar','आनंद अहिरवार'),size=12)],padding=35,bg='#e9f3ec',width=35,border_radius={'top':18,'right':18,'bottom':18,'left':18,'unit':'px'}),
 box([heading(L('MEET THE FOUNDER','संस्थापक का परिचय'),11,'iaai-section-label',tag='div',color='#0f766e'),heading('Anand Ahirwar',38,'iaai-section-title'),text(L('The focus is straightforward: help people understand insurance questions and avoid confusion. Learn more about the actual service scope and team.','उद्देश्य स्पष्ट है: बीमा से जुड़े सवालों को समझने और उलझन कम करने में मदद। वास्तविक सेवा दायरे और टीम की जानकारी लें।')),button(L('About the team →','टीम के बारे में →'),url('about',lang),True)],width=65)],direction='row')],'#f9faf8',65)
 faqitems=[('Do I need an account to ask a question?','क्या सवाल पूछने के लिए अकाउंट चाहिए?','A basic service enquiry can be submitted without an account. Private records require a verified account.','सामान्य enquiry अकाउंट के बिना भेज सकते हैं। निजी रिकॉर्ड के लिए verified account चाहिए।'),
 ('Can the team guarantee claim approval?','क्या क्लेम approval की गारंटी है?','No approval is guaranteed. Assessment and decisions remain with the insurer.','Approval की गारंटी नहीं है। समीक्षा और निर्णय बीमा कंपनी के अनुसार हैं।'),
 ('Is medical history required to register?','क्या रजिस्ट्रेशन में मेडिकल हिस्ट्री जरूरी है?','No. Profile and medical-history fields are optional.','नहीं। प्रोफ़ाइल और मेडिकल हिस्ट्री वैकल्पिक हैं।'),
 ('How do I check the current service fees?','वर्तमान सेवा शुल्क कैसे जानें?','Ask the team to explain the approved fee policy before proceeding.','आगे बढ़ने से पहले टीम से स्वीकृत fee policy समझें।')]
 faqs=section([heading(L('YOUR QUESTIONS, ANSWERED','आपके सवालों के जवाब'),11,'iaai-section-label',tag='div',color='#0f766e'),heading(L('Before you get started.','शुरुआत से पहले।'),40,'iaai-section-title')]+[box([heading(L(a,b),19,tag='h3'),text(L(c,d),size=15)],padding=18,cls='iaai-guide-row') for a,b,c,d in faqitems],padding=65)
 cta=section([box([box([heading(L('Not sure where to begin?','शुरुआत कहाँ से करें?'),37,color='#fff'),text(L('Tell us what you need help understanding.','बताएं कि क्या समझने में सहायता चाहिए।'),'#c9ded7')],width=65),button(L('Get guidance ↗','मार्गदर्शन लें ↗'),url('contact',lang))],direction='row')],'#102a43',55)
 save('home',lang,L('Home','मुखपृष्ठ'),'hi' if lang=='hi' else 'home',[hero,serviceblock,process,learn,founder,faqs,cta],L('Understand insurance with health, term, policy review and claim guidance.','स्वास्थ्य बीमा, टर्म, समीक्षा और क्लेम के लिए मार्गदर्शन।'))
 parent='home/hi' if lang=='hi' else None
 for key,slug,en,hi,body,bodyhi,symbol in services:
  details={
   'health':(['Family and individual needs','Questions for parents','Terms to discuss in a policy','Information to prepare'],['परिवार और व्यक्तिगत जरूरत','माता-पिता के लिए सवाल','पॉलिसी की शर्तें','तैयार रखने वाली जानकारी']),
   'term':(['Dependants and responsibilities','Protection needs to discuss','Questions about disclosure','Preparing for a consultation'],['आश्रित और जिम्मेदारियां','सुरक्षा की जरूरत','Disclosure से जुड़े सवाल','परामर्श की तैयारी']),
   'review':(['Key policy terms','Waiting periods and exclusions','Deductible / co-pay questions','Areas needing clarification'],['पॉलिसी की मुख्य शर्तें','Waiting period और exclusions','Deductible / co-pay के सवाल','स्पष्टीकरण के विषय']),
   'claim':(['Cashless issues','Reimbursement issues','Document queries','Assistance and its limits'],['Cashless की समस्या','Reimbursement की समस्या','दस्तावेज़ के सवाल','सहायता का दायरा'])}[key]
  content=[section([heading(L('YOUR NEXT STEP','आपका अगला कदम'),11,'iaai-section-label',tag='div',color='#0f766e'),heading(L(en,hi),54,'iaai-hero-title',tag='h1'),text(L(body,bodyhi),size=19)],'#f2f7f4',65),
  section([box([card(L(a,b),L('Bring your questions to a human conversation. Policy terms and insurer assessment must be reviewed for your situation.','अपने सवाल टीम से चर्चा करें। शर्तें और बीमा कंपनी की समीक्षा आपकी स्थिति के अनुसार समझें।'),'✓','#enquiry',lang) for a,b in zip(*details)],direction='row',flex_align_items='stretch')]),
  section([widget('iaai-guidance',{'service':{'health':'health','term':'term','review':'policy_review','claim':'claim_help'}[key]})],'#f5f7fa',60)]
  content[-1]['settings']['css_id']='enquiry'
  save(key,lang,L(en,hi),slug,content,L(body,bodyhi),parent=parent)
 save('about',lang,L('About','परिचय'),'about',[section([heading(L('THE PEOPLE BEHIND THE GUIDANCE','मार्गदर्शन के पीछे की टीम'),11,'iaai-section-label',tag='div'),heading('Anand Ahirwar',54,'iaai-hero-title',tag='h1'),text(L('It’s All About Insurance is a founder-led space for understandable insurance education and service enquiries.','It’s All About Insurance बीमा की सरल जानकारी और enquiries के लिए संस्थापक की पहल है।')),text(L('The actual business identity, authorization and commercial disclosures will be published only after verification.','व्यावसायिक पहचान, authorization और commercial disclosures सत्यापन के बाद प्रकाशित होंगे।')),button(L('Contact the team','टीम से संपर्क करें'),url('contact',lang))],'#f5f8f5')],parent=parent)
 save('contact',lang,L('Contact','संपर्क'),'contact',[section([widget('iaai-contact',{}),widget('iaai-guidance',{'service':'health'})],'#f5f7fa',60)],parent=parent)
 topics=[
 ('family-checklist','Family health insurance: questions to prepare','परिवार के स्वास्थ्य बीमा के लिए सवाल'),
 ('parents-checklist','A checklist for parents and senior citizens','माता-पिता के लिए checklist'),
 ('deductible-copay','Deductible and co-payment: discussion guide','Deductible और co-payment के सवाल'),
 ('cashless-reimbursement','Cashless and reimbursement: process questions','Cashless और reimbursement की प्रक्रिया'),
 ('claim-documents','Preparing information for claim assistance','क्लेम सहायता के लिए जानकारी तैयार करें'),
 ('disclosure-questions','Medical disclosure: questions for a review','Medical disclosure के सवाल'),
 ('term-dependants','Term protection and your responsibilities','टर्म सुरक्षा और जिम्मेदारियां'),
 ('policy-terms','Policy review: terms to ask about','पॉलिसी समीक्षा की जरूरी शर्तें')]
 save('learn',lang,L('Learning hub','जानकारी केंद्र'),'learn',[section([heading(L('LEARN AT YOUR PACE','अपने समय पर समझें'),11,'iaai-section-label',tag='div'),heading(L('Start with better questions.','बेहतर सवालों से शुरुआत।'),52,'iaai-hero-title',tag='h1'),text(L('Reviewed guides are being prepared. The topics below reflect common questions; full articles stay unpublished until factual review.','गाइड तैयार हो रही हैं। नीचे सामान्य सवालों के विषय हैं; तथ्य समीक्षा तक लेख प्रकाशित नहीं होंगे।'))]+[box([heading(L(en,hi),21,tag='h3'),text(L('Guide pending editorial review.','लेख की समीक्षा बाकी है।'),size=13)],padding=18,cls='iaai-guide-row') for _,en,hi in topics])],parent=parent)
 for slug,en,hi in topics:
  save('guide-'+slug,lang,L(en,hi),slug,[section([heading(L(en,hi),45,tag='h1'),text(L('Editorial draft — not approved for publication.','संपादकीय मसौदा — प्रकाशन स्वीकृत नहीं।')),text(L('Prepare your main question, family needs where relevant, the policy wording you want to discuss, and the dates of any related events. Do not send full identifiers or detailed medical reports in a public form. Ask the team to explain applicable terms using the actual policy and current insurer information. This draft must receive source references, author/reviewer attribution and a review date before publication.','मुख्य सवाल, संबंधित जरूरत, पॉलिसी की शर्तें और घटनाओं की तारीख तैयार रखें। सार्वजनिक form में पहचान विवरण या मेडिकल रिपोर्ट न भेजें। वास्तविक पॉलिसी और वर्तमान जानकारी के आधार पर टीम से शर्तें समझें। प्रकाशन से पहले स्रोत, लेखक, समीक्षक और समीक्षा तारीख जोड़ना आवश्यक है।'))])],draft=True,parent='learn/'+lang)
 save('stories',lang,L('Stories','अनुभव'),'stories',[section([heading(L('REAL CASES. VERIFIED DETAILS.','वास्तविक मामले। सत्यापित जानकारी।'),11,'iaai-section-label',tag='div'),heading(L('Every case has its own context.','हर मामले की अपनी स्थिति।'),49,'iaai-hero-title',tag='h1'),text(L('Approved case studies will be added after evidence review and publication consent. Pending cases will not be presented as successful outcomes.','साक्ष्य समीक्षा और प्रकाशन सहमति के बाद मामले जोड़े जाएंगे। लंबित मामलों को सफल परिणाम नहीं बताया जाएगा।')),button(L('Ask about claim help','क्लेम सहायता लें'),url('claim-help',lang))])],parent=parent)
 save('case-detail',lang,L('Case detail','मामले का विवरण'),'case-detail',[section([heading(L('Case awaiting review','मामले की समीक्षा बाकी'),45,tag='h1'),text(L('Add consent, anonymized context, reviewed evidence, dates and a precise outcome label before publication.','प्रकाशन से पहले सहमति, गुमनाम विवरण, साक्ष्य, तारीख और सही outcome label जोड़ें।'))])],draft=True,parent='stories/'+lang)
 for key,title,hi in [('privacy','Privacy notice','गोपनीयता नोटिस'),('terms','Account and service terms','अकाउंट और सेवा शर्तें'),('disclosures','Service disclosures','सेवा संबंधी जानकारी')]:
  save(key,lang,L(title,hi),key,[section([heading(L(title,hi),45,tag='h1'),text(L('Owner review required before registration, enquiry collection or real-report launch.','रजिस्ट्रेशन, enquiry collection या वास्तविक report launch से पहले owner review जरूरी है।')),text(L('Draft fields to complete: legal identity, actual service role, contact, purposes of collection, optional medical storage, sharing controls, approved retention and backup expiry, deletion/export workflow, commercial relationships and current fees. This template is not a legal approval or compliance certification.','पूरा करने वाले विवरण: कानूनी पहचान, सेवा भूमिका, संपर्क, जानकारी लेने का उद्देश्य, वैकल्पिक मेडिकल स्टोरेज, sharing controls, स्वीकृत रिटेंशन और backup expiry, deletion/export workflow, commercial relationships और वर्तमान शुल्क। यह template कानूनी स्वीकृति या compliance certification नहीं है।'))])],draft=True,parent=parent)
for key,slug,view,title in [
 ('account','account','auth','Your account'),('dashboard','my-dashboard','dashboard','My dashboard'),('profile','my-profile','profile','My profile'),('reports','my-reports','reports','My reports'),('treatment','my-treatment-history','treatment','Treatment history'),('enquiries','my-enquiries','enquiries','My enquiries'),('settings','account-settings','settings','Account settings')]:
 save(key,'en',title,slug,[section([widget('iaai-'+view,{})],'#f5f7fa',45)],private=True)
(ROOT/'elementor'/'manifest.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2))
print('Generated',len(manifest['templates']),'separate Elementor templates')
