import { test, expect } from '@playwright/test';
import { checkErrorNofification, clickContinueButton, selectRole, startNewApplication } from '../../utils/helpers';

const APPLICATION_TITLE = "Nuorisotoiminnan projektiavustus yhdistyksille"

test.skip(APPLICATION_TITLE, async ({ page }) => {
  await selectRole(page, 'REGISTERED_COMMUNITY');
  await startNewApplication(page, APPLICATION_TITLE)

  // Fill step 1
  await page.getByRole('textbox', { name: 'Sähköpostiosoite' }).fill('asadsdqwetest@example.org');
  await page.getByLabel('Yhteyshenkilö').fill('asddsa');
  await page.getByLabel('Puhelinnumero').fill('0234432243');
  await page.locator('#edit-community-address-community-address-select').selectOption({ index: 1 });
  await page.locator('#edit-bank-account-account-number-select').selectOption({ index: 1 });
  await page.getByLabel('Valitse vastaava henkilö').selectOption('0');
  await clickContinueButton(page);

  //Fill step 2
  await page.locator('#edit-kenelle-haen-avustusta').selectOption('Nuorisoyhdistys');
  await page.locator('#edit-acting-year').selectOption('2023');
  await page.locator('#edit-subventions-items-0-amount').fill('123,00€');
  await clickContinueButton(page);

  // Fill step 3
  await page.getByLabel('Kuinka monta 7-28 -vuotiasta helsinkiläistä jäsentä').fill('23');
  await page.getByLabel('Kuinka monta jäsentä tai aktiivista osallistujaa nuorten').fill('34');
  await clickContinueButton(page);

  // Fill step4
  await page.getByLabel('Projektin nimi').fill('asfsafs');
  await page.getByLabel('Projektin tavoitteet').fill('htrthrhtr');
  await page.getByLabel('Projektin sisältö').fill('rjttjtrj');
  await page.getByLabel('Projekti alkaa').fill('2023-09-30');
  await page.getByLabel('Projekti loppuu').fill('2023-11-19');
  await page.getByRole('textbox', { name: 'Kuinka monta 7-28 -vuotiasta helsinkiläistä projektiin osallistuu? ' }).fill('45');
  await page.getByRole('textbox', { name: 'Kuinka paljon projektin osallistujia on yhteensä?' }).fill('46');
  await page.getByRole('textbox', { name: 'Projektin paikka Projektin paikka' }).fill('eryreyyeyr');
  await clickContinueButton(page);

  // Fill step5
  await page.getByRole('textbox', { name: 'Omarahoitusosuuden kuvaus Omarahoitusosuuden kuvaus' }).fill('sdfdsfsfdsdf');
  await page.getByLabel('Omarahoitusosuus (€)').fill('3434');
  await page.getByLabel('Kuvaus tulosta').fill('ddfgdgf');
  await page.getByRole('group', { name: 'Muut tulot' }).getByLabel('Määrä (€)').fill('3534');
  await page.getByLabel('Kuvaus menosta').fill('ergerherhehr');
  await page.getByRole('group', { name: 'Menot' }).getByLabel('Määrä (€)').fill('346346');
  await clickContinueButton(page);

  // Fill step6
  await page.getByRole('textbox', { name: 'Lisätiedot' }).fill('sdgdgsdgdsgs');
  await page.locator('#edit-yhteison-saannot-attachment-upload').setInputFiles('e2e/utils/test.pdf');
  await page.locator('#edit-projektisuunnitelma-liite-attachment-upload').setInputFiles('e2e/utils/test.pdf');
  await page.locator('#edit-projektin-talousarvio-attachment-upload').setInputFiles('e2e/utils/test.pdf');
  await page.getByLabel('Lisäselvitys liitteistä').fill('sdgdsg');
  await page.getByRole('button', { name: 'Esikatseluun' }).click();

  // check data on confirmation page
  await page.getByLabel('Vakuutamme, että hakemuksessa ja sen liitteissä antamamme tiedot ovat oikeita, ja hyväksymme avustusehdot').check();
  await checkErrorNofification(page);

  // Submit application
  await page.getByRole('button', { name: 'Lähetä' }).click();
  await expect(page.getByRole('heading', { name: 'Avustushakemus lähetetty onnistuneesti' })).toBeVisible()
});

