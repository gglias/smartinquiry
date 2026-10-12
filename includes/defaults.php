<?php
/**
 * Default content: services, questions and every text of the form, in Greek and English.
 * Everything here can be changed in the admin; these are only the starting values.
 */
defined( 'ABSPATH' ) || exit;

function smi_t( $el, $en = null ) {
	return array( 'el' => $el, 'en' => null === $en ? $el : $en );
}

/** Options with stable ids (o1, o2, …): answers are stored as ids, so texts and order can change later. */
function smi_opts( array $labels ) {
	$out = array();
	foreach ( array_values( $labels ) as $i => $l ) {
		$out[] = array( 'id' => 'o' . ( $i + 1 ), 'label' => is_array( $l ) ? $l : smi_t( $l ) );
	}
	return $out;
}

function smi_q( $id, $type, $label, $opts = array(), $extra = array() ) {
	return array_merge(
		array(
			'id'      => $id,
			'type'    => $type,
			'req'     => 0,
			'other'   => 0,
			'label'   => $label,
			'hint'    => array(),
			'ph'      => array(),
			'opts'    => $opts ? smi_opts( $opts ) : array(),
			'show_if' => null,
		),
		$extra
	);
}

function smi_default_services() {
	$dont_know = smi_t( 'Δεν ξέρω', "I don't know" );
	$not_sure  = smi_t( 'Δεν ξέρω ακόμα', 'Not sure yet' );
	$optional  = smi_t( 'προαιρετικό', 'optional' );
	$budget_app = array( smi_t( 'Έως 5.000 €', 'Up to €5,000' ), smi_t( '5.000 – 15.000 €', '€5,000 – 15,000' ), smi_t( '15.000 – 40.000 €', '€15,000 – 40,000' ), smi_t( '40.000 € +', '€40,000+' ), $not_sure );
	$req       = array( 'req' => 1 );
	$req_other = array( 'req' => 1, 'other' => 1 );

	return array(
		array(
			'id' => 'app', 'on' => 1, 'icon' => 'phone',
			'label' => smi_t( 'Mobile app' ), 'desc' => smi_t( 'Εφαρμογή για iOS και Android', 'An app for iOS and Android' ),
			'details_title' => smi_t( 'Πείτε μας για την εφαρμογή', 'Tell us about the app' ),
			'details_sub'   => smi_t( 'Δεν χρειάζεται να έχετε όλες τις απαντήσεις.', 'You do not need to have all the answers.' ),
			'features_title' => smi_t( 'Τι θέλετε να κάνει η εφαρμογή;', 'What should the app do?' ),
			'features_sub'   => smi_t( 'Επιλέξτε όσα χρειάζεστε. Μπορείτε και κανένα.', 'Pick what you need. None is fine too.' ),
			'budget' => smi_opts( $budget_app ),
			'details' => array(
				smi_q( 'app_os', 'single', smi_t( 'Σε ποιες πλατφόρμες;', 'Which platforms?' ), array( 'iOS', 'Android', smi_t( 'Και στις δύο', 'Both' ), $dont_know ), $req ),
				smi_q( 'app_kind', 'single', smi_t( 'Τι είδους εφαρμογή;', 'What kind of app?' ), array( smi_t( 'Για e-shop', 'For an e-shop' ), smi_t( 'Κρατήσεις / Ραντεβού', 'Bookings / Appointments' ), smi_t( 'Πιστότητα πελατών', 'Customer loyalty' ), smi_t( 'Εσωτερική για το προσωπικό', 'Internal, for staff' ), 'Marketplace' ), $req_other ),
				smi_q( 'app_stage', 'single', smi_t( 'Σε ποιο στάδιο είστε;', 'What stage are you at?' ), array( smi_t( 'Μόνο ιδέα', 'Just an idea' ), smi_t( 'Έχω σχέδια / wireframes', 'I have designs / wireframes' ), smi_t( 'Υπάρχει ήδη εφαρμογή', 'There is an existing app' ) ), $req ),
			),
			'features' => array(
				smi_q( 'app_feat', 'multi', smi_t( 'Λειτουργίες', 'Features' ), array( smi_t( 'Λογαριασμοί χρηστών', 'User accounts' ), smi_t( 'Ειδοποιήσεις (push)', 'Push notifications' ), smi_t( 'Πληρωμές', 'Payments' ), smi_t( 'Χάρτες / GPS', 'Maps / GPS' ), 'Chat', smi_t( 'Κάμερα / QR', 'Camera / QR' ), smi_t( 'Λειτουργία χωρίς internet', 'Works offline' ), smi_t( 'Σύνδεση με site / ERP', 'Connects to website / ERP' ), smi_t( 'Πάνελ διαχείρισης', 'Admin panel' ), 'AI' ), array( 'other' => 1 ) ),
				smi_q( 'app_need', 'multi', smi_t( 'Τι θα χρειαστείτε από εμάς;', 'What will you need from us?' ), array( smi_t( 'Σχεδιασμό UI/UX', 'UI/UX design' ), smi_t( 'Δημοσίευση στα stores', 'Publishing to the app stores' ), smi_t( 'Συντήρηση', 'Maintenance' ) ) ),
			),
		),
		array(
			'id' => 'webapp', 'on' => 1, 'icon' => 'app',
			'label' => smi_t( 'Web app / Πλατφόρμα', 'Web app / Platform' ), 'desc' => smi_t( 'Portal, CRM, SaaS, αυτοματισμοί', 'Portals, CRM, SaaS, automation' ),
			'details_title' => smi_t( 'Πείτε μας για την πλατφόρμα', 'Tell us about the platform' ),
			'details_sub'   => smi_t( 'Λίγα βασικά για το τι θα κάνει και για ποιους.', 'A few basics about what it does and who it is for.' ),
			'features_title' => smi_t( 'Τι θέλετε να κάνει;', 'What should it do?' ),
			'features_sub'   => smi_t( 'Επιλέξτε όσα χρειάζεστε και περιγράψτε το αν θέλετε.', 'Pick what you need and describe it if you like.' ),
			'budget' => smi_opts( $budget_app ),
			'details' => array(
				smi_q( 'web_kind', 'single', smi_t( 'Τι θέλετε να φτιάξουμε;', 'What should we build?' ), array( smi_t( 'Portal πελατών', 'Customer portal' ), smi_t( 'CRM / Εσωτερικό σύστημα', 'CRM / Internal system' ), smi_t( 'Σύστημα κρατήσεων', 'Booking system' ), 'Marketplace', smi_t( 'SaaS προϊόν', 'SaaS product' ), smi_t( 'Αυτοματισμός διαδικασιών', 'Process automation' ) ), $req_other ),
				smi_q( 'web_users', 'single', smi_t( 'Ποιοι θα το χρησιμοποιούν;', 'Who will use it?' ), array( smi_t( 'Το προσωπικό', 'Staff' ), smi_t( 'Οι πελάτες', 'Customers' ), smi_t( 'Και οι δύο', 'Both' ) ), $req ),
				smi_q( 'web_count', 'single', smi_t( 'Πόσοι χρήστες περίπου;', 'Roughly how many users?' ), array( smi_t( 'Έως 10', 'Up to 10' ), '10–100', smi_t( '100–1.000', '100–1,000' ), smi_t( '1.000+', '1,000+' ) ), $req ),
				smi_q( 'web_stage', 'single', smi_t( 'Σε ποιο στάδιο είστε;', 'What stage are you at?' ), array( smi_t( 'Μόνο ιδέα', 'Just an idea' ), smi_t( 'Έχω σχέδια', 'I have designs' ), smi_t( 'Αντικαθιστά υπάρχον σύστημα', 'Replacing an existing system' ) ), $req ),
			),
			'features' => array(
				smi_q( 'web_feat', 'multi', smi_t( 'Λειτουργίες', 'Features' ), array( smi_t( 'Ρόλοι / Δικαιώματα', 'Roles / Permissions' ), smi_t( 'Dashboard / Αναφορές', 'Dashboard / Reports' ), smi_t( 'Πληρωμές / Συνδρομές', 'Payments / Subscriptions' ), smi_t( 'Σύνδεση με ERP / CRM / API', 'ERP / CRM / API integration' ), smi_t( 'Ειδοποιήσεις email / SMS', 'Email / SMS notifications' ), smi_t( 'Αρχεία / Έγγραφα', 'Files / Documents' ), smi_t( 'Πολλές γλώσσες', 'Multiple languages' ), 'AI' ), array( 'other' => 1 ) ),
				smi_q( 'web_desc', 'textarea', smi_t( 'Περιγράψτε το σε λίγες γραμμές', 'Describe it in a few lines' ), array(), array( 'hint' => $optional, 'ph' => smi_t( 'π.χ. Οι πελάτες μας να βλέπουν τις παραγγελίες και τα τιμολόγιά τους…', 'e.g. Our customers can see their orders and invoices…' ) ) ),
			),
		),
		array(
			'id' => 'ai', 'on' => 1, 'icon' => 'spark',
			'label' => smi_t( 'Λύσεις AI', 'AI solutions' ), 'desc' => smi_t( 'Chatbots, αυτοματισμοί, ανάλυση', 'Chatbots, automation, analysis' ),
			'details_title' => smi_t( 'Τι σας ενδιαφέρει στο AI;', 'What interests you in AI?' ),
			'details_sub'   => smi_t( 'Επιλέξτε όσα ισχύουν. Αν δεν είστε σίγουροι, σας προτείνουμε εμείς.', 'Pick all that apply. If you are not sure, we will suggest.' ),
			'features_title' => smi_t( 'Λίγα ακόμα για το AI', 'A little more about the AI' ),
			'features_sub'   => smi_t( 'Βοηθά να καταλάβουμε με τι θα δουλεύει και τι θέλετε να πετύχετε.', 'This helps us understand what it will work with and what you want to achieve.' ),
			'budget' => smi_opts( array( smi_t( 'Έως 1.500 €', 'Up to €1,500' ), smi_t( '1.500 – 5.000 €', '€1,500 – 5,000' ), smi_t( '5.000 – 15.000 €', '€5,000 – 15,000' ), smi_t( '15.000 € +', '€15,000+' ), $not_sure ) ),
			'details' => array(
				smi_q( 'ai_kind', 'multi', smi_t( 'Τι θέλετε να κάνει;', 'What should it do?' ), array( smi_t( 'Chatbot για το site', 'Chatbot for the website' ), smi_t( 'Βοηθός εξυπηρέτησης (email, Messenger)', 'Customer service assistant (email, Messenger)' ), smi_t( 'Αυτοματισμός εργασιών', 'Task automation' ), smi_t( 'Ανάλυση δεδομένων / Αναφορές', 'Data analysis / Reports' ), smi_t( 'Δημιουργία περιεχομένου', 'Content creation' ), smi_t( 'Custom εφαρμογή AI', 'Custom AI application' ), smi_t( 'Δεν ξέρω, θέλω συμβουλή', 'Not sure, I would like advice' ) ), $req ),
				smi_q( 'ai_where', 'multi', smi_t( 'Πού θα χρησιμοποιηθεί;', 'Where will it be used?' ), array( smi_t( 'Στο site', 'On the website' ), smi_t( 'Στο e-shop', 'In the e-shop' ), smi_t( 'Μέσα στην εταιρεία', 'Inside the company' ), smi_t( 'Σε εφαρμογή', 'In an app' ) ), $req ),
			),
			'features' => array(
				smi_q( 'ai_data', 'multi', smi_t( 'Με ποια δεδομένα θα δουλεύει;', 'What data will it work with?' ), array( smi_t( 'Κείμενα του site', 'Website content' ), smi_t( 'Κατάλογος προϊόντων', 'Product catalogue' ), smi_t( 'Έγγραφα / PDF', 'Documents / PDFs' ), 'CRM / ERP', 'Email', $dont_know ) ),
				smi_q( 'ai_goal', 'multi', smi_t( 'Τι θέλετε να πετύχετε;', 'What do you want to achieve?' ), array( smi_t( 'Λιγότερος χρόνος σε επαναλαμβανόμενες δουλειές', 'Less time on repetitive work' ), smi_t( 'Εξυπηρέτηση 24/7', '24/7 customer service' ), smi_t( 'Περισσότερες πωλήσεις', 'More sales' ), smi_t( 'Καλύτερες αποφάσεις', 'Better decisions' ) ), array( 'other' => 1 ) ),
			),
		),
		array(
			'id' => 'site', 'on' => 1, 'icon' => 'globe',
			'label' => smi_t( 'Ιστοσελίδα', 'Website' ), 'desc' => smi_t( 'Εταιρική, landing page, portfolio', 'Company site, landing page, portfolio' ),
			'details_title' => smi_t( 'Πείτε μας για την ιστοσελίδα', 'Tell us about the website' ),
			'details_sub'   => smi_t( 'Διαλέξτε ό,τι ταιριάζει περισσότερο. Τις λεπτομέρειες τις λέμε μαζί.', 'Pick what fits best. We will go through the details together.' ),
			'features_title' => smi_t( 'Τι θέλετε να έχει;', 'What should it include?' ),
			'features_sub'   => smi_t( 'Επιλέξτε όσα χρειάζεστε. Μπορείτε και κανένα.', 'Pick what you need. None is fine too.' ),
			'budget' => smi_opts( array( smi_t( 'Έως 1.000 €', 'Up to €1,000' ), smi_t( '1.000 – 2.500 €', '€1,000 – 2,500' ), smi_t( '2.500 – 5.000 €', '€2,500 – 5,000' ), smi_t( '5.000 € +', '€5,000+' ), $not_sure ) ),
			'details' => array(
				smi_q( 'site_new', 'single', smi_t( 'Νέα ή ανανέωση;', 'New or redesign?' ), array( smi_t( 'Νέα ιστοσελίδα', 'New website' ), smi_t( 'Ανανέωση υπάρχουσας', 'Redesign of an existing one' ) ), $req ),
				smi_q( 'site_url', 'text', smi_t( 'Διεύθυνση της υπάρχουσας', 'Address of the current site' ), array(), array( 'ph' => smi_t( 'π.χ. www.example.gr', 'e.g. www.example.com' ), 'show_if' => array( 'q' => 'site_new', 'opt' => 'o2' ) ) ),
				smi_q( 'site_kind', 'single', smi_t( 'Τι είδους ιστοσελίδα;', 'What kind of website?' ), array( smi_t( 'Εταιρική / Παρουσίασης', 'Company / Presentation' ), 'Landing page', smi_t( 'Portfolio / Προσωπική', 'Portfolio / Personal' ), smi_t( 'Blog / Περιοδικό', 'Blog / Magazine' ), smi_t( 'Τουρισμός', 'Tourism' ), smi_t( 'Εστίαση', 'Restaurant / Café' ) ), $req_other ),
				smi_q( 'site_pages', 'single', smi_t( 'Πόσες σελίδες περίπου;', 'Roughly how many pages?' ), array( '1', '2–5', '6–10', '11–20', '20+', $dont_know ), $req ),
			),
			'features' => array(
				smi_q( 'site_feat', 'multi', smi_t( 'Λειτουργίες', 'Features' ), array( smi_t( 'Πολλές γλώσσες', 'Multiple languages' ), smi_t( 'Blog / Νέα', 'Blog / News' ), smi_t( 'Κρατήσεις / Ραντεβού', 'Bookings / Appointments' ), smi_t( 'Φόρμες προσφοράς', 'Quote forms' ), smi_t( 'Περιοχή μελών', 'Members area' ), smi_t( 'Chatbot AI', 'AI chatbot' ), 'Newsletter', 'Google Reviews / Social', 'SEO', 'Analytics / Ads' ), array( 'other' => 1 ) ),
				smi_q( 'site_need', 'multi', smi_t( 'Τι θα χρειαστείτε από εμάς;', 'What will you need from us?' ), array( smi_t( 'Logo / Ταυτότητα', 'Logo / Brand identity' ), smi_t( 'Κείμενα', 'Copywriting' ), smi_t( 'Φωτογραφίες', 'Photos' ), 'Domain & Hosting', smi_t( 'Συντήρηση', 'Maintenance' ) ) ),
			),
		),
		smi_default_design_service(),
		array(
			'id' => 'other', 'on' => 1, 'icon' => 'chat',
			'label' => smi_t( 'Κάτι άλλο', 'Something else' ), 'desc' => smi_t( 'Ή δεν είστε σίγουροι ακόμα', 'Or you are not sure yet' ),
			'details_title' => smi_t( 'Πείτε μας τι έχετε στο μυαλό σας', 'Tell us what you have in mind' ),
			'details_sub'   => smi_t( 'Γράψτε ελεύθερα. Θα σας βοηθήσουμε να βρούμε την κατάλληλη λύση.', 'Write freely. We will help you find the right solution.' ),
			'features_title' => smi_t( '' ),
			'features_sub'   => smi_t( '' ),
			'budget' => smi_opts( array( smi_t( 'Έως 2.000 €', 'Up to €2,000' ), smi_t( '2.000 – 5.000 €', '€2,000 – 5,000' ), smi_t( '5.000 – 15.000 €', '€5,000 – 15,000' ), smi_t( '15.000 € +', '€15,000+' ), $not_sure ) ),
			'details' => array(
				smi_q( 'other_desc', 'textarea', smi_t( 'Περιγραφή', 'Description' ), array(), array( 'req' => 1, 'ph' => smi_t( 'π.χ. Θέλουμε να ανανεώσουμε το site μας και να προσθέσουμε online κρατήσεις…', 'e.g. We want to refresh our website and add online bookings…' ) ) ),
			),
			'features' => array(),
		),
	);
}

/** UI/UX design in Figma. Took the place of "E-shop" in 1.2.0 (see smi_migrate_settings()). */
function smi_default_design_service() {
	$req = array( 'req' => 1 );
	return array(
		'id' => 'design', 'on' => 1, 'icon' => 'brush',
		'label' => smi_t( 'Σχεδιασμός UI/UX', 'UI/UX Design' ), 'desc' => smi_t( 'Σχέδια σε Figma για app, πλατφόρμα ή site', 'Figma designs for apps, platforms and websites' ),
		'details_title' => smi_t( 'Πείτε μας για το design', 'Tell us about the design' ),
		'details_sub'   => smi_t( 'Λίγα βασικά για να καταλάβουμε τι θα σχεδιάσουμε.', 'A few basics so we understand what we will design.' ),
		'features_title' => smi_t( 'Τι θα περιλαμβάνει το design;', 'What should the design include?' ),
		'features_sub'   => smi_t( 'Επιλέξτε όσα χρειάζεστε. Μπορείτε και κανένα.', 'Pick what you need. None is fine too.' ),
		'budget' => smi_opts( array( smi_t( 'Έως 1.000 €', 'Up to €1,000' ), smi_t( '1.000 – 2.500 €', '€1,000 – 2,500' ), smi_t( '2.500 – 5.000 €', '€2,500 – 5,000' ), smi_t( '5.000 € +', '€5,000+' ), smi_t( 'Δεν ξέρω ακόμα', 'Not sure yet' ) ) ),
		'details' => array(
			smi_q( 'design_for', 'single', smi_t( 'Τι θέλετε να σχεδιάσουμε;', 'What should we design?' ), array( 'Mobile app', smi_t( 'Web app / Πλατφόρμα', 'Web app / Platform' ), smi_t( 'Ιστοσελίδα', 'Website' ), 'E-shop', smi_t( 'Dashboard / Πάνελ διαχείρισης', 'Dashboard / Admin panel' ) ), array( 'req' => 1, 'other' => 1 ) ),
			smi_q( 'design_new', 'single', smi_t( 'Νέο design ή ανανέωση;', 'New design or redesign?' ), array( smi_t( 'Νέο design από την αρχή', 'A new design from scratch' ), smi_t( 'Ανανέωση υπάρχοντος', 'Redesign of an existing one' ) ), $req ),
			smi_q( 'design_url', 'text', smi_t( 'Link του υπάρχοντος (site, app ή Figma)', 'Link to the current one (site, app or Figma)' ), array(), array( 'hint' => smi_t( 'προαιρετικό', 'optional' ), 'ph' => smi_t( 'π.χ. www.example.gr', 'e.g. www.example.com' ), 'show_if' => array( 'q' => 'design_new', 'opt' => 'o2' ) ) ),
			smi_q( 'design_screens', 'single', smi_t( 'Πόσες οθόνες περίπου;', 'Roughly how many screens?' ), array( smi_t( 'Έως 5', 'Up to 5' ), '6–15', '16–30', '30+', smi_t( 'Δεν ξέρω', "I don't know" ) ), $req ),
			smi_q( 'design_brand', 'single', smi_t( 'Έχετε ήδη εταιρική ταυτότητα;', 'Do you already have a brand identity?' ), array( smi_t( 'Ναι, logo και χρώματα', 'Yes, a logo and colours' ), smi_t( 'Μόνο logo', 'Only a logo' ), smi_t( 'Όχι ακόμα', 'Not yet' ) ), $req ),
		),
		'features' => array(
			smi_q( 'design_deliv', 'multi', smi_t( 'Παραδοτέα', 'Deliverables' ), array( 'Wireframes', smi_t( 'Τελικές οθόνες UI', 'Final UI screens' ), smi_t( 'Clickable prototype στο Figma', 'Clickable Figma prototype' ), 'Design system / Components', smi_t( 'Εκδόσεις για κινητό, tablet και desktop', 'Mobile, tablet and desktop versions' ), 'Dark mode', 'Icons / Illustrations', smi_t( 'Παράδοση στους developers (handoff)', 'Developer handoff' ) ), array( 'other' => 1 ) ),
			smi_q( 'design_ux', 'multi', smi_t( 'Έρευνα και UX', 'Research and UX' ), array( smi_t( 'Ανάλυση ανταγωνισμού', 'Competitor analysis' ), 'User flows', 'Personas', smi_t( 'Δοκιμές με χρήστες', 'User testing' ), smi_t( 'Έλεγχος προσβασιμότητας', 'Accessibility check' ) ) ),
			smi_q( 'design_dev', 'single', smi_t( 'Θα χρειαστείτε και ανάπτυξη;', 'Will you also need development?' ), array( smi_t( 'Ναι, από τη Smartup', 'Yes, by Smartup' ), smi_t( 'Όχι, μόνο το design', 'No, just the design' ), smi_t( 'Δεν ξέρω ακόμα', 'Not sure yet' ) ) ),
			smi_q( 'design_refs', 'textarea', smi_t( 'Sites ή apps που σας αρέσουν', 'Websites or apps you like' ), array(), array( 'hint' => smi_t( 'προαιρετικό', 'optional' ), 'ph' => smi_t( 'Links ή ονόματα, και τι σας αρέσει σε αυτά', 'Links or names, and what you like about them' ) ) ),
		),
	);
}

/** Options of "When would you like to start?", shared by all services. */
function smi_default_when() {
	return smi_opts( array( smi_t( 'Όσο πιο σύντομα', 'As soon as possible' ), smi_t( 'Σε 1–3 μήνες', 'In 1–3 months' ), smi_t( 'Σε 3–6 μήνες', 'In 3–6 months' ), smi_t( 'Δεν βιάζομαι', 'No rush' ) ) );
}

/**
 * Every other text of the form. Keys are grouped for the admin "Texts" screen (see smi_text_groups()).
 * {n}, {t}, {link}, {where} are filled in by the form.
 */
function smi_default_texts() {
	return array(
		// Information boxes.
		'info_service'  => smi_t( 'Δεν χρειάζεται να ξέρετε τεχνικούς όρους. Διαλέξτε το πιο κοντινό και τα υπόλοιπα τα λέμε μαζί.', 'No need to know technical terms. Pick the closest match and we will go through the rest together.' ),
		'info_features' => smi_t( 'Δεν είστε σίγουροι για κάτι; Αφήστε το. Θα το δούμε μαζί στη συζήτηση.', 'Not sure about something? Leave it. We will look at it together when we talk.' ),
		'info_plan'     => smi_t( 'Ο προϋπολογισμός μάς βοηθά να σας προτείνουμε τη σωστή λύση. Δεν σας δεσμεύει σε τίποτα.', 'Your budget helps us suggest the right solution. It does not commit you to anything.' ),
		'info_contact'  => smi_t( 'Χρησιμοποιούμε τα στοιχεία σας μόνο για να σας απαντήσουμε.', 'We use your details only to reply to you.' ),
		'reply_time'    => smi_t( 'Απαντάμε μέσα σε 1 εργάσιμη ημέρα.', 'We reply within 1 business day.' ),
		// Steps.
		'brand_title'   => smi_t( 'Αίτημα προσφοράς', 'Request a quote' ),
		'brand_sub'     => smi_t( 'Smartup' ),
		'summary'       => smi_t( 'Το αίτημά σας', 'Your request' ),
		'summary_empty' => smi_t( 'Οι απαντήσεις σας θα εμφανίζονται εδώ.', 'Your answers will appear here.' ),
		'step_of'       => smi_t( 'Βήμα {n} από {t}', 'Step {n} of {t}' ),
		'step_service'  => smi_t( 'Υπηρεσία', 'Service' ),
		'step_details'  => smi_t( 'Λεπτομέρειες', 'Details' ),
		'step_features' => smi_t( 'Λειτουργίες', 'Features' ),
		'step_plan'     => smi_t( 'Χρόνος & budget', 'Timing & budget' ),
		'step_contact'  => smi_t( 'Στοιχεία', 'Your details' ),
		's1_title'      => smi_t( 'Τι χρειάζεστε;', 'What do you need?' ),
		's1_sub'        => smi_t( 'Διαλέξτε μία υπηρεσία για να ξεκινήσουμε.', 'Choose one service to get started.' ),
		'plan_title'    => smi_t( 'Χρόνος και προϋπολογισμός', 'Timing and budget' ),
		'plan_sub'      => smi_t( 'Δύο γρήγορες ερωτήσεις και είμαστε σχεδόν έτοιμοι.', 'Two quick questions and we are almost done.' ),
		'contact_title' => smi_t( 'Πού να σας απαντήσουμε;', 'Where should we reply?' ),
		'contact_sub'   => smi_t( 'Τελευταίο βήμα. Θα επικοινωνήσουμε μαζί σας για να τα πούμε αναλυτικά.', 'Last step. We will get in touch to talk it through.' ),
		// Questions of steps 4 and 5.
		'q_when'        => smi_t( 'Πότε θέλετε να ξεκινήσουμε;', 'When would you like to start?' ),
		'q_budget'      => smi_t( 'Τι προϋπολογισμό έχετε κατά νου;', 'What budget do you have in mind?' ),
		'budget_hint'   => smi_t( 'χωρίς ΦΠΑ', 'excluding VAT' ),
		'q_notes'       => smi_t( 'Κάτι άλλο που πρέπει να ξέρουμε;', 'Anything else we should know?' ),
		'notes_ph'      => smi_t( 'Προθεσμίες, παραδείγματα sites που σας αρέσουν, ό,τι θέλετε.', 'Deadlines, websites you like, anything else.' ),
		'optional'      => smi_t( 'προαιρετικό', 'optional' ),
		'f_name'        => smi_t( 'Ονοματεπώνυμο', 'Full name' ),
		'f_name_ph'     => smi_t( 'Μαρία Παπαδοπούλου', 'Jane Smith' ),
		'f_email'       => smi_t( 'Email' ),
		'f_email_ph'    => smi_t( 'maria@example.gr', 'jane@example.com' ),
		'f_phone'       => smi_t( 'Τηλέφωνο', 'Phone' ),
		'f_phone_ph'    => smi_t( '69X XXX XXXX', '+44 7700 900123' ),
		'f_company'     => smi_t( 'Εταιρεία', 'Company' ),
		'f_company_ph'  => smi_t( 'Όνομα επιχείρησης', 'Company name' ),
		'q_pref'        => smi_t( 'Πώς προτιμάτε να επικοινωνήσουμε;', 'How would you like us to contact you?' ),
		'pref_phone'    => smi_t( 'Τηλέφωνο', 'Phone' ),
		'pref_email'    => smi_t( 'Email' ),
		'consent'       => smi_t( 'Συμφωνώ να χρησιμοποιηθούν τα στοιχεία μου για να λάβω απάντηση, σύμφωνα με την {link}.', 'I agree that my details are used to reply to my request, as described in the {link}.' ),
		'privacy_link'  => smi_t( 'Πολιτική Απορρήτου', 'Privacy Policy' ),
		'other'         => smi_t( 'Άλλο', 'Other' ),
		'other_ph'      => smi_t( 'Γράψτε τι εννοείτε', 'Tell us what you mean' ),
		// Buttons.
		'back'          => smi_t( 'Πίσω', 'Back' ),
		'next'          => smi_t( 'Συνέχεια', 'Continue' ),
		'submit'        => smi_t( 'Αποστολή αιτήματος', 'Send request' ),
		'sending'       => smi_t( 'Αποστολή…', 'Sending…' ),
		'clear'         => smi_t( 'Καθαρισμός', 'Clear' ),
		'clear_title'   => smi_t( 'Καθαρισμός φόρμας;', 'Clear the form?' ),
		'clear_text'    => smi_t( 'Θα σβηστούν όλες οι απαντήσεις σας και θα ξεκινήσετε από την αρχή.', 'All your answers will be deleted and you will start over.' ),
		'cancel'        => smi_t( 'Ακύρωση', 'Cancel' ),
		'clear_yes'     => smi_t( 'Καθαρισμός', 'Clear' ),
		'close'         => smi_t( 'Κλείσιμο', 'Close' ),
		// Errors.
		'err_single'    => smi_t( 'Επιλέξτε μία απάντηση.', 'Choose one answer.' ),
		'err_multi'     => smi_t( 'Επιλέξτε τουλάχιστον ένα.', 'Choose at least one.' ),
		'err_field'     => smi_t( 'Συμπληρώστε αυτό το πεδίο.', 'Please fill in this field.' ),
		'err_email'     => smi_t( 'Το email δεν φαίνεται σωστό. Ελέγξτε το, π.χ. onoma@example.gr.', 'This email does not look right. Check it, e.g. name@example.com.' ),
		'err_phone'     => smi_t( 'Γράψτε ένα τηλέφωνο με τουλάχιστον 10 ψηφία.', 'Enter a phone number with at least 10 digits.' ),
		'err_consent'   => smi_t( 'Χρειάζεται η συγκατάθεσή σας για να σας απαντήσουμε.', 'We need your consent to reply to you.' ),
		'err_send'      => smi_t( 'Δεν ήταν δυνατή η αποστολή. Ελέγξτε τη σύνδεσή σας και δοκιμάστε ξανά.', 'We could not send your request. Check your connection and try again.' ),
		'err_changed'   => smi_t( 'Η φόρμα άλλαξε στο μεταξύ. Ανανεώστε τη σελίδα και δοκιμάστε ξανά.', 'The form has changed in the meantime. Please reload the page and try again.' ),
		'err_rate'      => smi_t( 'Στείλατε πολλά αιτήματα σε λίγο χρόνο. Δοκιμάστε ξανά σε λίγο.', 'You have sent several requests in a short time. Please try again later.' ),
		'err_turnstile' => smi_t( 'Ολοκληρώστε τον έλεγχο ασφαλείας πιο πάνω και πατήστε ξανά αποστολή.', 'Please complete the security check above and send again.' ),
		'err_turnstile_down' => smi_t( 'Ο έλεγχος ασφαλείας δεν είναι διαθέσιμος αυτή τη στιγμή. Δοκιμάστε ξανά σε λίγο.', 'The security check is not available right now. Please try again in a moment.' ),
		// Thank you and PDF.
		'thanks'        => smi_t( 'Ευχαριστούμε', 'Thank you' ),
		'request'       => smi_t( 'Αίτημα', 'Request' ),
		'received'      => smi_t( 'Λάβαμε το αίτημά σας και θα επικοινωνήσουμε μαζί σας στο {where} μέσα σε 1 εργάσιμη ημέρα.', 'We received your request and will contact you at {where} within 1 business day.' ),
		'pdf_copy'      => smi_t( 'Κατεβάστε ένα αντίγραφο του αιτήματος σε PDF για το αρχείο σας.', 'Download a copy of your request as a PDF for your records.' ),
		'pdf_btn'       => smi_t( 'Κατεβάστε το αίτημα σε PDF', 'Download your request (PDF)' ),
		'restart'       => smi_t( 'Νέο αίτημα', 'New request' ),
		'pdf_title'     => smi_t( 'Αίτημα προσφοράς', 'Quote request' ),
		'pdf_service'   => smi_t( 'Υπηρεσία', 'Service' ),
		'pdf_need'      => smi_t( 'Τι χρειάζεστε', 'What you need' ),
		'pdf_contact'   => smi_t( 'Στοιχεία επικοινωνίας', 'Contact details' ),
		'pdf_via'       => smi_t( 'Επικοινωνία με', 'Contact by' ),
		'pdf_file'      => smi_t( 'aitima', 'request' ),
	);
}

function smi_defaults() {
	return array(
		'form'    => array(
			'services' => smi_default_services(),
			'when'     => smi_default_when(),
		),
		'texts'   => smi_default_texts(),
		'look'    => array(
			'accent'      => '#46e9d4',
			'accent_text' => '#000000',
			'panel'       => '#fafafa',
		),
		// Floating button in a bottom corner, shown while none of the page's own form buttons is on screen.
		'float'   => array(
			'enabled'  => 0,
			'position' => 'left',
			'label'    => smi_t( 'Ξεκινήστε το project σας', 'Start a project' ),
			'bg'       => '#f7f7f8',
			'color'    => '#000000',
			'mobile'   => 'show',
			'exclude'  => array(),
		),
		'notify'  => array(
			'enabled'    => 1,
			'attach'     => 1,
			'recipients' => array(),
			'subject'    => '',
		),
		'pdf'     => array(
			'logo_id' => 0,
			'company' => array( 'name' => '', 'website' => '', 'address' => '', 'phone' => '', 'email' => '', 'vat' => '' ),
			'footer'  => smi_t( '' ),
		),
		'privacy' => array(
			'pages'     => array(),
			'retention' => 12,
			'store_ip'  => 0,
		),
		// Cloudflare Turnstile: off until switched on. Empty keys: the ones from Contact Form 7 are used.
		'turnstile' => array(
			'enabled' => 0,
			'site'    => '',
			'secret'  => '',
		),
		// Entry link for people with SmartInquiry-only access: home/aitimata. Empty: no link.
		'access'    => array(
			'slug' => 'aitimata',
		),
		'ref_prefix' => 'SU',
	);
}

/** Default email subject, in the site's language. */
function smi_default_subject() {
	return 'el' === smi_default_lang() ? 'Νέο αίτημα: {service} από {name} ({ref})' : 'New request: {service} from {name} ({ref})';
}
