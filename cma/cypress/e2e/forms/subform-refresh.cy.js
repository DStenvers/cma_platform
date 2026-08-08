/**
 * Bewijs dat een subformulier-lijst zichzelf ververst nadat je er een record
 * aan toevoegt — zonder handmatig herladen.
 *
 * Waarom dit een E2E-test moet zijn: het bewijs loopt over drie schermen. Het
 * bovenliggende formulier draagt de tabbladen, het toevoegen gebeurt in een
 * eigen venster met parent-context, en pas bij het sluiten daarvan vraagt dat
 * venster aan de opener om precies dát tabblad opnieuw op te halen. Alleen een
 * draaiende site legt die keten in zijn geheel bloot.
 *
 * Het subformulier waarop dit draait is per site instelbaar, want lijsten en
 * records zijn dat ook. De standaardwaarden horen bij mijnRINO.
 */
const PARENT_FORM = Cypress.env('subformRefreshParentForm') || 'opleidingen';
const PARENT_ID   = Cypress.env('subformRefreshParentId')   || '137';
// data-id van het tabblad (het form-id dat het bovenliggende formulier kent)
const TAB_ID      = Cypress.env('subformRefreshTabId')      || '206';
// De naam waaronder de subform-API datzelfde subformulier meldt. Dat het twee
// namen zijn, is precies waar deze test over gaat.
const SUBFORM     = Cypress.env('subformRefreshSubform')    || 'opleidingen_vrijgesteld_blok';
// Veldnaam => waarde. De koppeling naar het bovenliggende record vult de CMA zelf.
const NIEUWE_WAARDEN = Cypress.env('subformRefreshFields') || {
    OMSCHRIJVING: null,   // null = de unieke merkwaarde van deze run
    AantalUur: '1'
};
// Het veld dat naar het bovenliggende record wijst. Dat de CMA dit zelf invult
// is geen luxe: het is doorgaans verplicht, dus zonder blijft opslaan hangen.
const PARENT_VELD = Cypress.env('subformRefreshParentField') || 'fkOpleiding';

describe('Subformulier ververst na toevoegen', () => {
    it('een net toegevoegd record staat meteen in de lijst', () => {
        const merk = 'cy-subform-' + Date.now();

        cy.loginAsAdmin();
        cy.visit(`/form.php?form=${PARENT_FORM}&id=${PARENT_ID}`);
        cy.get('.form-layout', { timeout: 20000 }).should('exist');
        cy.get('#subformTabs tab-item', { timeout: 20000 }).should('exist');

        // Het juiste tabblad openen
        cy.get(`#subformTabs tab-item[data-id="${TAB_ID}"]`).click({ force: true });
        cy.get('.subform-content .subform-pane.active, .subform-content', { timeout: 20000 })
            .should('exist');
        cy.wait(2000);

        // Het merk mag er nog niet staan
        cy.get('.subform-content').should('not.contain', merk);

        // Toevoegen vanuit de toolbar van dit subformulier. Op de knop staan
        // panes van andere tabbladen ook in de DOM, dus selecteer op subform-id.
        cy.get(`.subform-content [data-action="subform-add"][data-subform-id="${SUBFORM}"]`,
            { timeout: 20000 }).click({ force: true });

        // Het toevoegvenster invullen en opslaan
        const venster = () => cy.get('.lib_sidepanel_container iframe, .lib_window_dialog iframe',
            { timeout: 20000 }).last().its('0.contentDocument.body').should('not.be.empty')
            .then(cy.wrap);

        // Het venster is met parent-context geopend, dus de koppeling naar het
        // bovenliggende record hoort al ingevuld te staan.
        venster().find(`[name="${PARENT_VELD}"]`, { timeout: 20000 })
            .should('have.value', PARENT_ID);

        Object.entries(NIEUWE_WAARDEN).forEach(([naam, waarde]) => {
            venster().find(`[name="${naam}"]`, { timeout: 20000 })
                .clear({ force: true })
                .type(waarde === null ? merk : waarde, { force: true });
        });
        venster().find('[data-action="save"]').first().click({ force: true });

        // Na een geslaagde save sluit het venster zichzelf en vraagt het de
        // opener om dít tabblad opnieuw op te halen. Zonder herladen moet het
        // nieuwe record dus vanzelf in de lijst verschijnen.
        cy.get('.subform-content', { timeout: 40000 }).should('contain', merk);
    });
});
