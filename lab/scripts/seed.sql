-- =============================================================
--  UrbanUpC ? Donn?es de test r?alistes (Universite Paris Cite)
--  Ce fichier DOIT ?tre sauvegard? en UTF-8 (sans BOM).
--  V?rifier dans votre IDE : Encoding = UTF-8.
-- =============================================================

USE corpnet_db;

-- Force utf8mb4 pour cette session d'import
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Utilisateurs — 1 admin + 36 étudiants Master (Université Paris Cité)
-- admin:adminupc.330088
-- students:upc2026
-- -----------------------------------------------------------
INSERT INTO users (username, password, email, first_name, last_name, role, department, phone) VALUES
-- Admin
('admin',              MD5('adminupc.330088'), 'admin@universite-paris-cite.local',              'Sophie',          'Lamarque',        'admin', 'Direction Informatique', '+33 1 42 00 01 00'),
-- Étudiants Master
('p.babin',            MD5('upc2026'), 'p.babin@universite-paris-cite.local',            'Philippine',      'Babin',           'user', 'Master', NULL),
('y.bayoudh',          MD5('upc2026'), 'y.bayoudh@universite-paris-cite.local',          'Yasmine',         'Bayoudh',         'user', 'Master', NULL),
('w.benbrahim',        MD5('upc2026'), 'w.benbrahim@universite-paris-cite.local',        'Wilfried',        'Ben Brahim',      'user', 'Master', NULL),
('z.bensassi',         MD5('upc2026'), 'z.bensassi@universite-paris-cite.local',         'Zeineb',          'Ben Sassi',       'user', 'Master', NULL),
('o.bencherif',        MD5('upc2026'), 'o.bencherif@universite-paris-cite.local',        'Othman',          'Bencherif',       'user', 'Master', NULL),
('s.bouabcha',         MD5('upc2026'), 's.bouabcha@universite-paris-cite.local',         'Sofia Karina',    'Bouabcha',        'user', 'Master', NULL),
('f.boudoua',          MD5('upc2026'), 'f.boudoua@universite-paris-cite.local',          'Farah',           'Boudoua',         'user', 'Master', NULL),
('b.brahimi',          MD5('upc2026'), 'b.brahimi@universite-paris-cite.local',          'Bijied',          'Brahimi',         'user', 'Master', NULL),
('j.dohou',            MD5('upc2026'), 'j.dohou@universite-paris-cite.local',            'Jordan',          'Dohou',           'user', 'Master', NULL),
('g.gad',              MD5('upc2026'), 'g.gad@universite-paris-cite.local',              'Ghaydaa',         'Gad',             'user', 'Master', NULL),
('a.gilardi',          MD5('upc2026'), 'a.gilardi@universite-paris-cite.local',          'Avi',             'Gilardi',         'user', 'Master', NULL),
('g.glazman',          MD5('upc2026'), 'g.glazman@universite-paris-cite.local',          'Gabriel',         'Glazman',         'user', 'Master', NULL),
('l.guemmache',        MD5('upc2026'), 'l.guemmache@universite-paris-cite.local',        'Lyna',            'Guemmache',       'user', 'Master', NULL),
('a.guerrouf',         MD5('upc2026'), 'a.guerrouf@universite-paris-cite.local',         'Amar',            'Guerrouf',        'user', 'Master', NULL),
('s.habaz',            MD5('upc2026'), 's.habaz@universite-paris-cite.local',            'Sofiane',         'Habaz',           'user', 'Master', NULL),
('m.hadjsadok',        MD5('upc2026'), 'm.hadjsadok@universite-paris-cite.local',        'Mohammed Nazim',  'Hadj Sadok',      'user', 'Master', NULL),
('i.ikene',            MD5('upc2026'), 'i.ikene@universite-paris-cite.local',            'Imed',            'Ikene',           'user', 'Master', NULL),
('a.khemakhem',        MD5('upc2026'), 'a.khemakhem@universite-paris-cite.local',        'Ayman',           'Khemakhem',       'user', 'Master', NULL),
('s.khomsi',           MD5('upc2026'), 's.khomsi@universite-paris-cite.local',           'Sofiane',         'Khomsi',          'user', 'Master', NULL),
('a.lucic',            MD5('upc2026'), 'a.lucic@universite-paris-cite.local',            'Antoine',         'Lucic',           'user', 'Master', NULL),
('m.messaoud-nacer',   MD5('upc2026'), 'm.messaoud-nacer@universite-paris-cite.local',   'Maria',           'Messaoud-Nacer',  'user', 'Master', NULL),
('l.milic',            MD5('upc2026'), 'l.milic@universite-paris-cite.local',            'Luka',            'Milic',           'user', 'Master', NULL),
('t.pereiraoliveira',  MD5('upc2026'), 't.pereiraoliveira@universite-paris-cite.local',  'Tomas',           'Pereira Oliveira','user', 'Master', NULL),
('e.ranjalahy',        MD5('upc2026'), 'e.ranjalahy@universite-paris-cite.local',        'Elisa',           'Ranjalahy',       'user', 'Master', NULL),
('v.ravichandran',     MD5('upc2026'), 'v.ravichandran@universite-paris-cite.local',     'Vishakan',        'Ravichandran',    'user', 'Master', NULL),
('w.rosewarn',         MD5('upc2026'), 'w.rosewarn@universite-paris-cite.local',         'William',         'Rosewarn',        'user', 'Master', NULL),
('u.schmith',          MD5('upc2026'), 'u.schmith@universite-paris-cite.local',          'Ulrich',          'Schmith',         'user', 'Master', NULL),
('i.silakehal',        MD5('upc2026'), 'i.silakehal@universite-paris-cite.local',        'Imene',           'Si Lakehal',      'user', 'Master', NULL),
('n.singh',            MD5('upc2026'), 'n.singh@universite-paris-cite.local',            'Navdeep',         'Singh',           'user', 'Master', NULL),
('a.soleau',           MD5('upc2026'), 'a.soleau@universite-paris-cite.local',           'Adrien',          'Soleau',          'user', 'Master', NULL),
('i.taharount',        MD5('upc2026'), 'i.taharount@universite-paris-cite.local',        'Imane',           'Taharount',       'user', 'Master', NULL),
('n.terkmani',         MD5('upc2026'), 'n.terkmani@universite-paris-cite.local',         'Narimane',        'Terkmani',        'user', 'Master', NULL),
('c.yaici',            MD5('upc2026'), 'c.yaici@universite-paris-cite.local',            'Chiheb Chahine',  'Yaici',           'user', 'Master', NULL),
('k.zamouri',          MD5('upc2026'), 'k.zamouri@universite-paris-cite.local',          'Khadija',         'Zamouri',         'user', 'Master', NULL),
('e.zapolny',          MD5('upc2026'), 'e.zapolny@universite-paris-cite.local',          'Elena',           'Zapolny',         'user', 'Master', NULL),
('m.zianeberroudja',   MD5('upc2026'), 'm.zianeberroudja@universite-paris-cite.local',   'Mohammed Yassine','Ziane Berroudja', 'user', 'Master', NULL);

-- -----------------------------------------------------------
-- Documents classifi?s (50 documents r?alistes)
-- IDOR : owner_id non v?rifi? ? tout utilisateur peut acc?der ? /api/docs.php?id=X
-- -----------------------------------------------------------
INSERT INTO documents (owner_id, title, content, classification, file_name) VALUES
-- Documents admin (id=1)
(1, CONVERT(UNHEX('506C616E205374726174C3A9676971756520323032352D32303237') USING utf8mb4),
 CONVERT(UNHEX('4178652031203A20457870616E73696F6E20737572206C6573206D61726368C3A97320444143482065742042654E654C75780A4178652032203A204469676974616C69736174696F6E206465732070726F63657373757320696E7465726E65730A4178652033203A20526563727574656D656E74203132302045545020737572203320616E730A427564676574207072C3A9766973696F6E6E656C203A2034354D20455552') USING utf8mb4),
 'secret', 'plan_strategique_2025.pdf'),

(1, CONVERT(UNHEX('526170706F72742041756469742053C3A96375726974C3A920534920E2809420436F6E666964656E7469656C') USING utf8mb4),
 CONVERT(UNHEX('506F696E747320637269746971756573206964656E74696669C3A973203A0A2D205365727665757220496E7472616E6574203A2041706163686520322E342E34392073616E73207061746368204356452D323032312D34313737330A2D204D6F7473206465207061737365204D443520656E20626173650A2D20416273656E6365206465205741460A5265636F6D6D616E646174696F6E73203A206D6967726174696F6E20757267656E746520766572732041706163686520322E342E35342B') USING utf8mb4),
 'secret', 'audit_secu_2024_CONFIDENTIEL.pdf'),

(1, CONVERT(UNHEX('43726564656E7469616C7320496E66726173747275637475726520E28094204E45205041532044495354524942554552') USING utf8mb4),
 'vCenter Admin: admin / VMw@re2024!\nMySQL Root: R00tUrbanUpC!2024\nCisco ASA: enable / M3ridian@cisco\nVPN concentrateur: 10.0.0.1 / vpnadmin / Vpn@2024',
 'secret', 'credentials_infra.txt'),

(1, 'Liste des Serveurs Internes',
 'DC01: 10.0.1.10 (Windows Server 2019)\nDC02: 10.0.1.11 (Windows Server 2019)\nSRV-FILE: 10.0.1.20 (Ubuntu 22.04)\nSRV-DB: 10.0.1.30 (Ubuntu 22.04 / MySQL)\nSRV-INTRANET: 10.0.1.40 (Ubuntu 22.04 / Apache)',
 'confidential', 'inventaire_serveurs.xlsx'),

-- Documents RH (id=2, j.dupont manager)
(2, 'Grille des Salaires 2024',
 CONVERT(UNHEX('4E697665617520312028546563686E696369656E293A2032382D33356B204555520A4E697665617520322028496E67C3A96E69657572293A2033382D35326B204555520A4E69766561752033202853656E696F72293A2035352D37356B204555520A4E6976656175203420284D616E61676572293A2036352D39306B204555520A4E697665617520352028446972656374696F6E293A2039352D3133306B20455552') USING utf8mb4),
 'confidential', 'grille_salaires_2024.xlsx'),

(2, 'Dossiers Disciplinaires',
 CONVERT(UNHEX('52C3A966C3A972656E636520442D323032342D3030333A204D2E205065746974204C7563617320E28094204176657274697373656D656E7420706F757220616273656E636520696E6A757374696669C3A9650A52C3A966C3A972656E636520442D323032342D3030373A204D6D652053696D6F6E2043616D696C6C6520E28094204D69736520656E2064656D657572650A52C3A966C3A972656E636520442D323032342D3031323A204D2E20476172636961204E69636F6C617320E28094204C6963656E6369656D656E7420656E20636F757273') USING utf8mb4),
 'confidential', 'dossiers_disciplinaires.pdf'),

(2, 'Plan de Recrutement Q1 2025',
 CONVERT(UNHEX('506F73746573206F757665727473203A203320446576204261636B656E6420285048502F4A617661292C2032204465764F70732C203120525353492C2031204442410A42756467657420616C6C6F75C3A9203A203435306B204555520A436162696E6574732070617274656E6169726573203A20486179732C204D69636861656C20506167652C20526F626572742048616C66') USING utf8mb4),
 'internal', 'recrutement_Q1_2025.docx'),

-- Documents comptabilit? (id=3, m.martin)
(3, CONVERT(UNHEX('42756467657420436F6E736F6C6964C3A92032303234') USING utf8mb4),
 CONVERT(UNHEX('43412072C3A9616C6973C3A9203A2038372E334D2045555220282B3132252076732032303233290A454249544441203A2031342E324D2045555220286D617267652031362E3325290A496E76657374697373656D656E7473203A20382E374D204555520A5472C3A9736F7265726965206E65747465203A2032332E314D20455552') USING utf8mb4),
 'confidential', 'budget_consolide_2024.xlsx'),

(3, CONVERT(UNHEX('4E6F746520646520467261697320E2809420436F6D6974C3A920446972656374696F6E204F63742032303234') USING utf8mb4),
 CONVERT(UNHEX('44C3A9706C6163656D656E74204E594320283420706572736F6E6E657329203A20313820343530204555520A53C3A96D696E616972652043616E6E6573203A20323320383030204555520A456E7465727461696E6D656E7420636C69656E7473203A203820323030204555520A546F74616C203A2035302034353020455552') USING utf8mb4),
 'internal', 'notes_frais_codir_oct2024.pdf'),

-- Documents commercial (id=4, p.bernard)
(4, CONVERT(UNHEX('436F6E747261747320436C69656E7473205374726174C3A9676971756573') USING utf8mb4),
 CONVERT(UNHEX('436C69656E743A20415841204672616E636520E2809420436F6E74726174203320616E7320E2809420322E334D204555522F616E20E280942052656E6F7576656C6C656D656E74206D61727320323032350A436C69656E743A20546F74616C20456E65726769657320E2809420436F6E7472617420636164726520E2809420312E384D204555522F616E0A436C69656E743A20534E434620E2809420456E206EC3A9676F63696174696F6E20E2809420457374696D6174696F6E20342E324D20455552') USING utf8mb4),
 'confidential', 'contrats_clients_2024.xlsx'),

(4, CONVERT(UNHEX('4F6666726520436F6D6D65726369616C6520546F74616C456E65726769657320E28094204452414654') USING utf8mb4),
 CONVERT(UNHEX('50726F706F736974696F6E20746563686E697175652065742066696E616E6369C3A872650A536F6C7574696F6E3A20557262616E55704320436C6F75642053756974652076332E320A507269783A20312037353020303030204555522048542F616E0A52656D697365206EC3A9676F6369C3A9653A2038250A436F6E746163743A204D2E204475626F69732C2044534920546F74616C456E6572676965732C203036203132203334203536203738') USING utf8mb4),
 'confidential', 'offre_total_energies_DRAFT.pdf'),

-- Documents marketing (id=5, a.lefebvre)
(5, CONVERT(UNHEX('43616D7061676E65205134203230323420E280942052C3A973756C74617473') USING utf8mb4),
 CONVERT(UNHEX('4C696E6B6564496E204164733A203134356B20696D7072657373696F6E732C2043545220322E33252C203320333430206C656164730A53616C6F6E20495420506172746E6572733A20383920636F6E7461637473207175616C696669C3A9730A524F4920676C6F62616C2063616D7061676E653A2033343025') USING utf8mb4),
 'internal', 'campagne_q4_resultats.pptx'),

-- Documents juridique (id=6, t.moreau manager)
(6, 'Contrat NDA Projet Phoenix',
 CONVERT(UNHEX('4163636F726420646520636F6E666964656E7469616C6974C3A92062696C6174C3A972616C20656E74726520556E69766572736974C3A920506172697320436974C3A920657420446174615661756C7420496E632E0A447572C3A9653A203520616E7320C3A020636F6D707465722064752031352F30312F323032340A50C3A972696DC3A87472653A20546563686E6F6C6F67696520646520636869666672656D656E7420686F6D6F6D6F727068650A50C3A96E616C6974C3A9733A203530306B20455552207061722076696F6C6174696F6E') USING utf8mb4),
 'secret', 'nda_projet_phoenix.pdf'),

(6, CONVERT(UNHEX('4C697469676520536F6369616C20E28094204166666169726520447570756973') USING utf8mb4),
 CONVERT(UNHEX('44656D616E646575723A204D2E2044757075697320416E746F696E65202865782D656D706C6F79C3A9290A4F626A65743A204C6963656E6369656D656E74206162757369660A4D6F6E74616E742072C3A9636C616DC3A93A2031343520303030204555520A41756469656E636520544A2050617269733A203134206D61727320323032350A41766F63617420557262616E5570433A204D6520466F6E7461696E652C20436162696E657420466F6E7461696E652026204173736F6369C3A973') USING utf8mb4),
 'secret', 'litige_dupuis_confidentiel.pdf'),

-- Documents R&D (id=7, c.simon)
(7, CONVERT(UNHEX('5370C3A963696669636174696F6E7320546563686E69717565732050726F6A65742050686F656E6978') USING utf8mb4),
 CONVERT(UNHEX('417263686974656374757265206D6963726F736572766963657320E2809420323320636F6D706F73616E74730A537461636B3A20476F20312E3231202B20506F737467726553514C203135202B204B61666B610A41504920476174657761793A204B6F6E6720332E340A496E6672617374727563747572653A204B756265726E6574657320312E3238207375722041575320454B530A44617465206465206C6976726169736F6E20657374696DC3A9653A2054332032303235') USING utf8mb4),
 'confidential', 'specs_projet_phoenix_v2.3.pdf'),

(7, CONVERT(UNHEX('526170706F72742064652056756C6EC3A9726162696C6974C3A97320E280942050656E7465737420496E7465726E65') USING utf8mb4),
 CONVERT(UNHEX('43524954495155453A20496E6A656374696F6E2053514C2073757220656E64706F696E74202F6170692F7365617263680A43524954495155453A2049444F52207375722074C3A96CC3A96368617267656D656E7420646F63756D656E74730A484155543A2055706C6F61642073616E73207265737472696374696F6E207479706520646520666963686965720A484155543A2053657373696F6E73207072C3A976697369626C657320284D44352074696D657374616D70290A4D4F59454E3A205853532073746F636BC3A92064616E7320616E6E6F6E6365730A434F4D4D454E54414952453A20526170706F727420C3A0204E452050415320646966667573657220686F727320C3A971756970652073C3A96375726974C3A9') USING utf8mb4),
 'secret', 'rapport_pentest_interne_2024.pdf'),

-- Documents publics (accessibles ? tous)
(1, CONVERT(UNHEX('52C3A8676C656D656E7420496E74C3A9726965757220556E69766572736974C3A920506172697320436974C3A9') USING utf8mb4), CONVERT(UNHEX('52C3A8676C656D656E7420696E74C3A97269657572206170706C696361626C6520C3A020746F7573206C657320656D706C6F79C3A9732E2E2E') USING utf8mb4), 'public', 'reglement_interieur.pdf'),
(1, 'Guide Onboarding Nouveaux Arrivants', 'Bienvenue chez Universite Paris Cite ! Ce guide vous accompagne...', 'public', 'guide_onboarding.pdf'),
(1, 'Charte Informatique', 'Usage des ressources informatiques de Universite Paris Cite...', 'public', 'charte_informatique.pdf'),
(2, CONVERT(UNHEX('43616C656E647269657220436F6E67C3A9732032303235') USING utf8mb4), CONVERT(UNHEX('4A6F7572732066C3A97269C3A973206574206665726D65747572657320657863657074696F6E6E656C6C657320323032352E2E2E') USING utf8mb4), 'public', 'calendrier_conges_2025.pdf'),

-- Autres documents internes
(8, CONVERT(UNHEX('5265636865726368652052264420E2809420416C676F726974686D6520646520436F6D7072657373696F6E') USING utf8mb4), CONVERT(UNHEX('52C3A973756C74617473207072C3A96C696D696E616972657320737572206C5C27616C676F726974686D65204C5A2D557262616E5570432076322E2E2E') USING utf8mb4), 'internal', 'rd_compression_results.pdf'),
(9, CONVERT(UNHEX('526170706F727420436F6D6D65726369616C2052C3A967696F6E20537564') USING utf8mb4), CONVERT(UNHEX('43412072C3A967696F6E205375642054332032303234203A20342E324D204555522E2E2E') USING utf8mb4), 'internal', 'rapport_commercial_sud_t3.xlsx'),
(10, 'Tickets Support Semaine 42', CONVERT(UNHEX('53796E7468C3A8736520696E636964656E74732073656D61696E652034322F3230323420E28094203334207469636B657473207472616974C3A9732E2E2E') USING utf8mb4), 'internal', 'support_semaine42.pdf'),
(11, 'Plan Transport et Logistique 2025', 'Nouveaux prestataires logistiques Q1 2025...', 'internal', 'plan_logistique_2025.docx'),
(12, CONVERT(UNHEX('436F6D7074652D52656E64752052C3A9756E696F6E20524820E280942031352F31302F32303234') USING utf8mb4), CONVERT(UNHEX('506F696E74732061626F7264C3A973203A20C3A9766F6C7574696F6E206772696C6C652073616C617269616C652C20706C616E20666F726D6174696F6E2E2E2E') USING utf8mb4), 'internal', 'cr_rh_151024.pdf'),
(13, CONVERT(UNHEX('436CC3B47475726520436F6D707461626C65204F63746F6272652032303234') USING utf8mb4), CONVERT(UNHEX('52C3A9636F6E63696C696174696F6E2062616E63616972652065742070726F766973696F6E732061752033312F31302F323032342E2E2E') USING utf8mb4), 'internal', 'cloture_oct2024.xlsx'),
(14, 'Pipeline Commercial Q4 2024', CONVERT(UNHEX('4F70706F7274756E6974C3A97320656E20636F757273203A203132206465616C7320706F757220756E20746F74616C20646520382E374D204555522E2E2E') USING utf8mb4), 'internal', 'pipeline_q4_2024.xlsx'),
(15, CONVERT(UNHEX('536368C3A96D612052C3A97365617520E2809420434F4E464944454E5449454C') USING utf8mb4), CONVERT(UNHEX('4172636869746563747572652072C3A97365617520556E69766572736974C3A920506172697320436974C3A920E2809420564C414E2C20444D5A2C207365676D656E74732E2E2E') USING utf8mb4), 'confidential', 'schema_reseau_confidentiel.pdf'),
(1, CONVERT(UNHEX('4D6F74732064652050617373652057692D466920E28094205369746573') USING utf8mb4), 'Paris HQ: UrbanUpC@WiFi2024\nLyon: Lyon@UrbanUpC!\nBordeaux: Bx2024UrbanUpC', 'confidential', 'wifi_passwords.txt'),
(1, CONVERT(UNHEX('416363C3A8732056504E20436F6C6C61626F72617465757273') USING utf8mb4), 'Serveur: vpn.universite-paris-cite.com\nProtocole: IKEv2\nPSK: M3r1d1an_VPN_2024!\nCertificat: urbanup-vpn-ca.crt', 'secret', 'config_vpn_collaborateurs.pdf');

-- -----------------------------------------------------------
-- Annonces (XSS stock? ? contenu non sanitis?)
-- -----------------------------------------------------------
INSERT INTO announcements (author_id, title, content, pinned) VALUES
(1, CONVERT(UNHEX('4D61696E74656E616E636520706C616E696669C3A96520E280942053616D6564692035204F63746F627265') USING utf8mb4),
 CONVERT(UNHEX('556E65206D61696E74656E616E63652064752073797374C3A86D65206427696E666F726D6174696F6E20657374207072C3A9767565206C652073616D6564692035206F63746F6272652064652032326820C3A02036682E204C657320736572766963657320696E7472616E6574207365726F6E7420696E746572726F6D7075732E') USING utf8mb4),
 1),
(2, CONVERT(UNHEX('52617070656C203A2044C3A9636C61726174696F6E20436F6E67C3A973204E6FC3AB6C206176616E74206C652031352F3131') USING utf8mb4),
 CONVERT(UNHEX('4D6572636920646520736F756D657474726520766F732064656D616E64657320646520636F6E67C3A97320706F7572206C612070C3A972696F6465206465204E6FC3AB6C206176616E74206C65203135206E6F76656D62726520766961206C276573706163652052482E') USING utf8mb4),
 0),
(1, CONVERT(UNHEX('4D69736520C3A0206A6F757220506F6C6974697175652053C3A96375726974C3A9') USING utf8mb4),
 CONVERT(UNHEX('4C61206E6F7576656C6C6520706F6C6974697175652064652073C3A96375726974C3A920696E666F726D61746971756520656E74726520656E207669677565757220617520316572206A616E7669657220323032352E20566575696C6C657A206C69726520617474656E746976656D656E74206C612063686172746520646973706F6E69626C652064616E73206C612073656374696F6E20446F63756D656E74732E') USING utf8mb4),
 1),
(15, CONVERT(UNHEX('53C3A96D696E6169726520416E6E75656C20E280942053617665207468652044617465') USING utf8mb4),
 CONVERT(UNHEX('4C652073C3A96D696E6169726520616E6E75656C20556E69766572736974C3A920506172697320436974C3A92073652064C3A9726F756C657261206C6573203134206574203135206E6F76656D627265203230323420C3A0204C796F6E2E204C657320696E736372697074696F6E7320736F6E74206F757665727465732E') USING utf8mb4),
 0);

-- -----------------------------------------------------------
-- Logs d'audit initiaux (historique r?aliste)
-- -----------------------------------------------------------
INSERT INTO audit_logs (user_id, username, action, resource, ip_address, status, created_at) VALUES
(1, 'admin',      'LOGIN',          '/login',                   '10.0.0.5',    'success', NOW() - INTERVAL 2 DAY),
(2, 'j.dupont',   'LOGIN',          '/login',                   '10.0.0.12',   'success', NOW() - INTERVAL 2 DAY),
(3, 'm.martin',   'LOGIN',          '/login',                   '10.0.0.23',   'success', NOW() - INTERVAL 1 DAY),
(3, 'm.martin',   'DOWNLOAD_DOC',   '/api/docs.php?id=3',       '10.0.0.23',   'success', NOW() - INTERVAL 1 DAY),
(1, 'admin',      'VIEW_USERS',     '/api/users.php',           '10.0.0.5',    'success', NOW() - INTERVAL 1 DAY),
(4, 'p.bernard',  'LOGIN',          '/login',                   '10.0.0.34',   'success', NOW() - INTERVAL 6 HOUR),
(4, 'p.bernard',  'UPLOAD_FILE',    '/upload.php',              '10.0.0.34',   'success', NOW() - INTERVAL 5 HOUR),
(0, NULL,         'LOGIN_FAILED',   '/login',                   '10.0.0.99',   'failure', NOW() - INTERVAL 3 HOUR),
(0, NULL,         'LOGIN_FAILED',   '/login',                   '10.0.0.99',   'failure', NOW() - INTERVAL 3 HOUR),
(0, NULL,         'LOGIN_FAILED',   '/login',                   '10.0.0.99',   'failure', NOW() - INTERVAL 3 HOUR);

-- -----------------------------------------------------------
-- Services IT/RH/Finance/Juridique (catalogue intranet)
-- (?tait cr?? en migration live ? maintenant dans le sch?ma initial)
-- -----------------------------------------------------------
INSERT INTO services (name, description, category, icon, contact_name, contact_email, status, created_by) VALUES
-- Informatique
('Helpdesk IT',             CONVERT(UNHEX('537570706F727420746563686E69717565206E69766561752031206574203220E28094207469636B6574732C2064C3A970616E6E61676520706F73746573') USING utf8mb4),          'informatique', 'fa-headset',         'Nicolas Garcia',   'n.garcia@universite-paris-cite.local',   'active',      1),
(CONVERT(UNHEX('56504E202620416363C3A8732044697374616E74') USING utf8mb4),     CONVERT(UNHEX('436F6E66696775726174696F6E20657420737570706F727420616363C3A8732056504E20636F6C6C61626F72617465757273') USING utf8mb4),                    'informatique', 'fa-shield-alt',      'Nicolas Garcia',   'n.garcia@universite-paris-cite.local',   'active',      1),
('Messagerie & Teams',      'Administration Exchange, Microsoft Teams, licences M365',              'informatique', 'fa-envelope',        'Sophie Lamarque',  'admin@universite-paris-cite.local',      'active',      1),
(CONVERT(UNHEX('47657374696F6E2064657320416363C3A873') USING utf8mb4),       CONVERT(UNHEX('4372C3A96174696F6E20636F6D707465732C2064726F6974732C20416374697665204469726563746F7279') USING utf8mb4),                           'informatique', 'fa-key',             'Sophie Lamarque',  'admin@universite-paris-cite.local',      'active',      1),
('Infra & Serveurs',        'Supervision serveurs, sauvegardes, virtualisation VMware',             'informatique', 'fa-server',          CONVERT(UNHEX('56616CC3A972696520526F757373656175') USING utf8mb4), 'v.rousseau@universite-paris-cite.local', 'active',      1),
(CONVERT(UNHEX('53C3A96375726974C3A920496E666F726D617469717565') USING utf8mb4),   CONVERT(UNHEX('47657374696F6E206465732076756C6EC3A9726162696C6974C3A9732C206175646974732C205349454D') USING utf8mb4),                            'informatique', 'fa-lock',            CONVERT(UNHEX('56616CC3A972696520526F757373656175') USING utf8mb4), 'v.rousseau@universite-paris-cite.local', 'maintenance', 1),
-- RH
('Ressources Humaines',     'Recrutement, contrats, formation, paie',                              'rh',           'fa-users',           'Jean Dupont',      'j.dupont@universite-paris-cite.local',   'active',      2),
(CONVERT(UNHEX('466F726D6174696F6E202620436F6D70C3A974656E636573') USING utf8mb4), CONVERT(UNHEX('506C616E2064652064C3A976656C6F7070656D656E742C20652D6C6561726E696E672C2063657274696669636174696F6E73') USING utf8mb4),                   'rh',           'fa-graduation-cap',  'Isabelle Blanc',   'i.blanc@universite-paris-cite.local',    'active',      2),
-- Finance
(CONVERT(UNHEX('436F6D70746162696C6974C3A9') USING utf8mb4),            CONVERT(UNHEX('4661637475726174696F6E2C206E6F7465732064652066726169732C20636CC3B4747572657320636F6D707461626C6573') USING utf8mb4),                    'finance',      'fa-calculator',      'Marie Martin',     'm.martin@universite-paris-cite.local',   'active',      3),
(CONVERT(UNHEX('446972656374696F6E2046696E616E6369C3A87265') USING utf8mb4),    CONVERT(UNHEX('4275646765742C207072C3A9766973696F6E732C207265706F7274696E6720646972656374696F6E') USING utf8mb4),                             'finance',      'fa-chart-line',      'Sarah David',      's.david@universite-paris-cite.local',    'active',     11),
-- Juridique
(CONVERT(UNHEX('4A7572696469717565202620436F6E666F726D6974C3A9') USING utf8mb4),  CONVERT(UNHEX('436F6E74726174732C206C6974696765732C20524750442C20636F6E666F726D6974C3A92072C3A9676C656D656E7461697265') USING utf8mb4),                   'juridique',    'fa-balance-scale',   'Thomas Moreau',    't.moreau@universite-paris-cite.local',   'active',      6),
-- Logistique
('Logistique',              'Transport, approvisionnements, gestion des prestataires',             'logistique',   'fa-truck',           CONVERT(UNHEX('5261706861C3AB6C2054686F6D6173') USING utf8mb4),   'r.thomas@universite-paris-cite.local',   'active',     12);
