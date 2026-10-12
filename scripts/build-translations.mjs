import fs from 'node:fs';
import path from 'node:path';

const translations = {
  'Register': 'Anmelden',
  'Register for {activity}': 'Anmeldung für {activity}',
  'Leave your details to register for {activity}. We will get back to you soon.': 'Ich freue mich, dass du dich für „{activity}“ anmeldest. Hinterlasse deine Kontaktdaten, wir melden uns bald bei dir.',
  'Full name': 'Vor- und Nachname',
  'Phone number': 'Telefonnummer',
  'Email address': 'E-Mail-Adresse',
  'Message (optional)': 'Nachricht (optional)',
  'Choose an option': 'Option auswählen',
  'Please select an option': 'Bitte wähle eine Option',
  'I agree to the processing of my data and accept the {terms} and {privacy}.': 'Ich stimme der elektronischen Verarbeitung und Speicherung meiner Daten zu und akzeptiere die {terms} und die {privacy}.',
  'terms and conditions': 'AGBs',
  'privacy policy': 'Datenschutzvereinbarung',
  'Send registration': 'Anmeldung senden',
  'Thank you! Your registration for {activity} has been sent. We will contact you soon.': 'Danke! Deine Anmeldung für {activity} wurde gesendet. Wir melden uns bald bei dir.',
  'Close': 'Schließen',
  'Leave this field empty': 'Dieses Feld leer lassen',
  'Register another person': 'Eine weitere Person anmelden',
  'Please enable JavaScript to use this registration form.': 'Bitte aktiviere JavaScript, um dieses Anmeldeformular zu verwenden.',
  'Activity': 'Aktivität',
  'Astrid: add at least one activity option in the block editor.': 'Astrid: Bitte füge mindestens eine Aktivitätsoption im Block-Editor hinzu.',
  'Astrid Registration': 'Astrid Anmeldung',
  'Astrid Multiple options': 'Astrid Mehrere Optionen',
  'Detected activity': 'Erkannte Aktivität',
  'Page or post title': 'Seiten- oder Beitragstitel',
  'Uses the first Heading in the nearest enclosing Group; otherwise uses the page or post title.': 'Verwendet die erste Überschrift in der nächstgelegenen umschließenden Gruppe, sonst den Seiten- oder Beitragstitel.',
  'Form display': 'Formularanzeige',
  'Use dashboard default': 'Dashboard-Vorgabe verwenden',
  'Popup': 'Popup',
  'Reveal below the button': 'Unter dem Button einblenden',
  'Always visible': 'Immer sichtbar',
  'Activity options': 'Aktivitätsoptionen',
  'One option per line. Include duration, price or any other details in the option text.': 'Eine Option pro Zeile. Dauer, Preis oder andere Angaben können direkt im Optionstext stehen.',
  'Add at least one option in the block settings before publishing.': 'Füge vor dem Veröffentlichen mindestens eine Option in den Block-Einstellungen hinzu.',
  'Button text (optional)': 'Button-Text (optional)',
  'Form preview — registrations are sent only on the published page.': 'Formularvorschau — Anmeldungen werden nur auf der veröffentlichten Seite gesendet.',
  'Sending…': 'Wird gesendet…',
  'Something went wrong. Please try again.': 'Da ist leider etwas schiefgegangen. Bitte versuch es erneut.',
  'The connection was interrupted. Please try again.': 'Die Verbindung wurde unterbrochen. Bitte versuch es erneut.',
  'Please complete the required fields and accept the consent checkbox.': 'Bitte fülle alle Pflichtfelder aus und bestätige die Zustimmung.',
  'Please choose an option before registering.': 'Bitte wähle vor der Anmeldung eine Option.',
  'This form is no longer valid. Reload the page and try again.': 'Dieses Formular ist nicht mehr gültig. Bitte lade die Seite neu und versuch es erneut.',
  'This activity is not available for registration.': 'Diese Aktivität ist nicht zur Anmeldung verfügbar.',
  'Too many attempts. Please wait a minute and try again.': 'Zu viele Versuche. Bitte warte eine Minute und versuch es erneut.',
  'Registration: %s': 'Anmeldung: %s',
  'The email could not be sent. Please try again later.': 'Die E-Mail konnte nicht gesendet werden. Bitte versuch es später erneut.',
  'Enter a valid recipient email address. The previous address was kept.': 'Bitte gib eine gültige Empfängeradresse ein. Die bisherige Adresse wurde beibehalten.',
  'Place a Heading and an Astrid block inside a native Group. Astrid identifies the activity automatically from that heading.': 'Platziere eine Überschrift und einen Astrid-Block in einer nativen Gruppe. Astrid erkennt die Aktivität automatisch anhand dieser Überschrift.',
  'Delivery and display': 'Versand und Anzeige',
  'Recipient email': 'Empfänger-E-Mail',
  'Terms page URL': 'URL der AGB-Seite',
  'Privacy page URL': 'URL der Datenschutzseite',
  'Default form display': 'Standard-Formularanzeige',
  'Form text': 'Formulartexte',
  'Leave a text field blank to follow the WordPress site language. Use {activity} for the detected activity and {option} for the selected option. Consent text uses {terms} and {privacy} for links.': 'Lasse ein Textfeld leer, um der WordPress-Websitesprache zu folgen. Verwende {activity} für die erkannte Aktivität und {option} für die gewählte Option. Der Zustimmungstext verwendet {terms} und {privacy} für Links.',
  'Registration button': 'Anmeldebutton',
  'Form heading': 'Formularüberschrift',
  'Introduction': 'Einleitung',
  'Name field': 'Namensfeld',
  'Phone field': 'Telefonfeld',
  'Email field': 'E-Mail-Feld',
  'Message field': 'Nachrichtenfeld',
  'Options label': 'Beschriftung der Optionen',
  'Options placeholder': 'Platzhalter der Optionen',
  'Consent wording': 'Zustimmungstext',
  'Terms link text': 'Text des AGB-Links',
  'Privacy link text': 'Text des Datenschutzlinks',
  'Submit button': 'Absenden-Button',
  'Thank-you message': 'Bestätigungsnachricht',
  'Button appearance': 'Button-Design',
  'Theme styling uses the native WordPress Button classes. Custom styling applies to both registration and submit buttons.': 'Das Theme-Design verwendet die nativen WordPress-Button-Klassen. Eigene Stile gelten für Anmelde- und Absenden-Buttons.',
  'Style': 'Stil',
  'Inherit theme': 'Vom Theme übernehmen',
  'Custom': 'Eigener Stil',
  'Background color': 'Hintergrundfarbe',
  'Text color': 'Textfarbe',
  'Corner radius (px)': 'Eckenradius (px)',
  'Vertical padding (px)': 'Vertikaler Innenabstand (px)',
  'Horizontal padding (px)': 'Horizontaler Innenabstand (px)',
  'Font size (px)': 'Schriftgröße (px)'
};

// GNU gettext MO format: WordPress can load these without a PHP build dependency.
function compileMo(locale) {
  const catalog = { '': `Project-Id-Version: Astrid 2.0.0\nLanguage: ${locale}\nContent-Type: text/plain; charset=UTF-8\nPlural-Forms: nplurals=2; plural=(n != 1);\n`, ...translations };
  const entries = Object.entries(catalog).sort(([a], [b]) => Buffer.compare(Buffer.from(a), Buffer.from(b)));
  const originals = entries.map(([a]) => Buffer.from(a));
  const values = entries.map(([, b]) => Buffer.from(b));
  const n = entries.length;
  const header = Buffer.alloc(28 + n * 16);
  header.writeUInt32LE(0x950412de, 0);
  header.writeUInt32LE(n, 8);
  header.writeUInt32LE(28, 12);
  header.writeUInt32LE(28 + n * 8, 16);
  const data = [];
  let offset = header.length;
  for (const [table, strings] of [[28, originals], [28 + n * 8, values]]) {
    strings.forEach((value, index) => {
      header.writeUInt32LE(value.length, table + index * 8);
      header.writeUInt32LE(offset, table + index * 8 + 4);
      data.push(value, Buffer.from([0]));
      offset += value.length + 1;
    });
  }
  return Buffer.concat([header, ...data]);
}

const directory = path.resolve('astrid/languages');
fs.mkdirSync(directory, { recursive: true });
for (const locale of ['de_DE', 'de_DE_formal', 'de_AT', 'de_CH', 'de_CH_informal']) {
  fs.writeFileSync(path.join(directory, `astrid-${locale}.mo`), compileMo(locale));
}
const quote = text => JSON.stringify(text);
fs.writeFileSync(path.join(directory, 'astrid-de_DE.po'), Object.entries(translations).map(([id, translation]) => `msgid ${quote(id)}\nmsgstr ${quote(translation)}\n`).join('\n'));
console.log(`Built German catalogs (${Object.keys(translations).length} messages).`);
