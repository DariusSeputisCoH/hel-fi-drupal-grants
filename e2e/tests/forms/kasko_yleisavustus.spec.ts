import { test, expect } from '@playwright/test';
import { checkErrorNofification, clickContinueButton, selectRole, startNewApplication } from '../../utils/helpers';

const APPLICATION_TITLE = "Kasvatus ja koulutus: yleisavustuslomake";

test(APPLICATION_TITLE, async ({ page }) => {
  await selectRole(page, 'REGISTERED_COMMUNITY');
  await startNewApplication(page, APPLICATION_TITLE)

  // Fill step 1
  await page.getByRole('textbox', { name: 'Sähköpostiosoite' }).fill('asadsdqwetest@example.org');
  await page.getByLabel('Yhteyshenkilö').fill('asddsa');
  await page.getByLabel('Puhelinnumero').fill('0234432243');
  await page.locator('#edit-community-address-community-address-select').selectOption({ index: 1 });
  await page.locator('#edit-bank-account-account-number-select').selectOption({ index: 1 });
  await page.getByLabel('Valitse vastaava henkilö').selectOption({ index: 1 });
  await clickContinueButton(page);

  //Fill step 2
  await page.getByLabel('Vuosi, jolle haen avustusta').selectOption('2023');
  await page.locator('#edit-subventions-items-0-amount').fill('128,00€');
  await page.getByRole('textbox', { name: 'Lyhyt kuvaus haettavan / haettavien avustusten käyttötarkoituksista' }).fill('lyhyt kuvasu');
  await page.getByLabel('Kuvaus lainoista ja takauksista').fill('asdadsdadaas');
  await page.getByLabel('Kuvaus tiloihin liittyvästä tuesta').fill('sdfdfsfdsdsf');
  await clickContinueButton(page);

  // Fill step 3
  await page.getByRole('textbox', { name: 'Toiminnan kuvaus' }).fill('asffsafsasfa');
  await page.getByText('Ei', { exact: true }).click();
  await page.locator('#edit-fee-person').fill('64');
  await page.locator('#edit-fee-community').fill('64');
  await page.getByRole('textbox', { name: 'Henkilöjäseniä yhteensä Henkilöjäseniä yhteensä' }).fill('123');
  await page.getByRole('textbox', { name: 'Helsinkiläisiä henkilöjäseniä yhteensä' }).fill('22');
  await page.getByRole('textbox', { name: 'Yhteisöjäseniä Yhteisöjäseniä' }).fill('44');
  await page.getByRole('textbox', { name: 'Helsinkiläisiä yhteisöjäseniä yhteensä' }).fill('55');
  await clickContinueButton(page);

  // Fill step 4
  await page.getByRole('textbox', { name: 'Lisätiedot' }).fill('qwfqwfqwfwfqfwq');
  await page.getByRole('group', { name: 'Yhteisön säännöt Yhteisön säännöt' }).getByLabel('Liite toimitetaan myöhemmin').check();
  await page.getByRole('group', { name: 'Vahvistettu tilinpäätös' }).getByLabel('Liite toimitetaan myöhemmin').check();
  await page.getByRole('group', { name: 'Vahvistettu toimintakertomus' }).getByLabel('Liite toimitetaan myöhemmin').check();
  await page.getByRole('group', { name: 'Vahvistettu tilin- tai toiminnantarkastuskertomus' }).getByLabel('Liite toimitetaan myöhemmin').check();
  await page.locator('#edit-vuosikokouksen-poytakirja--wrapper').getByText('Liite toimitetaan myöhemmin').click();
  await page.locator('#edit-toimintasuunnitelma--wrapper').getByText('Liite toimitetaan myöhemmin').click();
  await page.locator('#edit-talousarvio--wrapper').getByText('Liite toimitetaan myöhemmin').click();
  await page.getByLabel('Lisäselvitys liitteistä').fill('sdfdfsdfsdfsdfsdfsdfs');
  await page.getByRole('button', { name: 'Esikatseluun >' }).click();

  // Step 5: Check preview page
  await page.getByText('Tarkista lähetyksesi. Lähetyksesi on valmis vasta, kun painat "Lähetä"-painikett').click();
  await expect(page.getByText('Helsingin kaupungin myöntämiin avustuksiin sovelletaan seuraavia avustusehtoja.')).toBeVisible();
  await page.getByLabel('Vakuutamme, että hakemuksessa ja sen liitteissä antamamme tiedot ovat oikeita, ja hyväksymme avustusehdot').check();
  await checkErrorNofification(page);

  // Submit application
  await page.getByRole('button', { name: 'Lähetä' }).click();
  await expect(page.getByRole('heading', { name: 'Avustushakemus lähetetty onnistuneesti' })).toBeVisible()
});

