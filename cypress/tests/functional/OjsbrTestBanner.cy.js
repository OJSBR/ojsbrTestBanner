/**
 * @file cypress/tests/functional/OjsbrTestBanner.cy.js
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Functional tests: the site administrator enables the plugin for the whole
 * installation and writes the message of the band in the plugin's settings;
 * the message is read back from the server, and is what a reader sees at the
 * bottom of a journal page and what the editorial back end shows. A PDF galley
 * viewer has no band; a journal manager cannot switch it off; and with the
 * plugin off no page has it.
 *
 * Parameters (--env): contextPath, adminUser, adminPassword, and managerUser and
 * managerPassword for a journal manager, who must not be able to switch the band
 * off (captcha on login must be off for the run). The defaults match the data
 * set of PKP's continuous integration.
 *
 * The settings page of the journal is loaded once for the whole run (a second load leaves
 * the web server of PKP's CI waiting until the page load times out), so test
 * isolation is off. The plugin and its message are put back as they were.
 */

describe('Test environment banner', {testIsolation: false}, function() {
	const contextPath = Cypress.env('contextPath') || 'publicknowledge';
	const adminUser = Cypress.env('adminUser') || 'admin';
	const adminPassword = Cypress.env('adminPassword') || 'admin';
	// A journal manager of the data set of PKP's CI; elsewhere, pass one with --env.
	const managerUser = Cypress.env('managerUser') || (contextPath === 'publicknowledge' ? 'rvaca' : null);
	const managerPassword = Cypress.env('managerPassword') || (managerUser ? managerUser + managerUser : null);

	const rowName = 'ojsbrtestbannerplugin';
	const formSelector = 'form[id="ojsbrTestBannerSettingsForm"]';
	const message = 'Cypress staging ' + Date.now() + ' — use the "live" portal';
	// Whether the plugin was on and the message it had, captured once and put back in after().
	let original = null;

	// ---- OJSBR spec helpers (padrão v2) ----

	const pageUrl = (path) => '/index.php/' + contextPath + (path ? '/' + path : '');
	// The plugins grid of the journal lists the site-wide plugins too, with their
	// actions for the site administrator only: it is the way in on a site with a
	// single journal, where OJS 3.5 hides the plugins tab of the site settings.
	const gridUrl = (op) => pageUrl('$$$call$$$/grid/settings/plugins/settings-plugin-grid/' + op);

	const waitJQuery = () => cy.window({timeout: 60000}).should((win) => expect(win.jQuery && win.jQuery.active).to.eq(0));

	const request = (options) => cy.window({log: false}).then((win) => cy.request(Object.assign(
		typeof options === 'string' ? {url: options} : options,
		{headers: Object.assign({'User-Agent': win.navigator.userAgent}, (typeof options === 'string' ? {} : options.headers) || {})}
	)));

	const login = (username, password) => {
		cy.clearCookies();
		request(pageUrl('login')).then((response) => {
			const token = /name="csrfToken" value="([^"]+)"/.exec(response.body)[1];
			const action = /<form[^>]*id="login"[^>]*action="([^"]+)"/.exec(response.body)[1];
			request({method: 'POST', url: action, form: true, body: {csrfToken: token, username: username, password: password}, log: false});
		});
		cy.visit(pageUrl('submissions') + '?reload=' + Date.now());
		cy.get('body').then(($body) => {
			if ($body.find('form#login').length) {
				cy.get('form#login input[name="username"]').type(username, {delay: 0});
				cy.get('form#login input[name="password"]').type(password, {delay: 0, log: false});
				cy.get('form#login').submit();
				cy.get('form#login', {timeout: 30000}).should('not.exist');
			}
		});
	};

	// A component call with the token of the page (only back-end pages have it).
	const post = (url, fields) => cy.window({log: false}).then((win) => {
		const body = new URLSearchParams();
		Object.keys(fields).forEach((name) => [].concat(fields[name]).forEach((value) => body.append(name, value)));
		body.append('csrfToken', win.pkp.currentUser.csrfToken);
		return cy.wrap(
			win.fetch(url, {method: 'POST', credentials: 'same-origin', body: body}).then((response) => response.text()),
			{log: false, timeout: 30000}
		);
	});

	const get = (url) => cy.window({log: false}).then((win) => cy.wrap(
		win.fetch(url, {credentials: 'same-origin'}).then((response) => response.json()),
		{log: false, timeout: 60000}
	));

	// What the body shows after its content: the band, as the browser renders it.
	const band = () => cy.window({log: false}).then((win) => {
		const content = win.getComputedStyle(win.document.body, '::after').content;
		return content === 'none' || content === 'normal' ? null : JSON.parse(content);
	});

	const waitFormHandler = () => cy.window({timeout: 30000}).should((win) => {
		expect(win.jQuery(formSelector).data('pkp.handler'), 'form handler').to.exist;
	});

	// Opens the settings modal from the grid, on the page already open.
	const openSettings = () => {
		cy.get('a[id*="-row-' + rowName + '-settings-button-"]', {timeout: 30000}).then(($link) => {
			if (!$link.is(':visible')) {
				cy.get('tr[id$="-row-' + rowName + '"] a.show_extras').first().click();
			}
		});
		cy.get('a[id*="-row-' + rowName + '-settings-button-"]').first().click({force: true});
		waitJQuery();
		waitFormHandler();
	};

	const closeModal = () => {
		cy.get(formSelector + ' a[id^="cancelFormButton"], ' + formSelector + ' .cancelButton').first().click();
		cy.get(formSelector).should('not.exist');
	};

	// ---- end of helpers ----

	before(function() {
		login(adminUser, adminPassword);

		// The plugins grid is what records a plugin copied into place as
		// installed; it also tells whether it is on.
		get(gridUrl('fetch-grid')).then((grid) => {
			const row = new RegExp('-row-' + rowName + '"[\\s\\S]*?<input[^>]*type="checkbox"[^>]*>').exec(grid.content || '');
			expect(row, 'the plugin is listed').to.exist;
			if (original === null) {
				original = {enabled: /\bchecked\b/.test(row[0]), messages: {}};
			}
		});

		// The message it had, from its own form.
		cy.then(() => original.enabled && get(gridUrl('manage') + '?verb=settings&plugin=' + rowName + '&category=generic')).then((form) => {
			if (!form) {
				return;
			}
			const doc = new DOMParser().parseFromString(form.content, 'text/html');
			doc.querySelectorAll('input[name^="message["]').forEach((input) => {
				original.messages[input.name] = input.value;
			});
		});

		post(gridUrl('enable'), {plugin: rowName, category: 'generic', disableNotification: 1}).then((answer) => {
			expect(answer, 'the plugin was switched on').to.contain('"status":true');
		});

		// The one load of the settings page with the plugins grid.
		cy.visit(pageUrl('management/settings/website') + '?reload=' + Date.now());
	});

	after(function() {
		cy.window({log: false}).then((win) => {
			if (!win.pkp || !win.pkp.currentUser) {
				cy.visit(pageUrl('submissions'));
			}
		});
		// The message goes back through the plugin's own form, as the administrator saves it.
		cy.then(() => post(
			gridUrl('manage') + '?verb=settings&plugin=' + rowName + '&category=generic&save=1',
			original.messages
		)).then((answer) => expect(answer, 'the message was put back').to.contain('"status":true'));
		cy.then(() => !original.enabled && post(gridUrl('disable'), {plugin: rowName, category: 'generic', disableNotification: 1}));
		get(gridUrl('fetch-grid')).then((grid) => {
			const row = new RegExp('-row-' + rowName + '"[\\s\\S]*?<input[^>]*type="checkbox"[^>]*>').exec(grid.content || '');
			expect(/\bchecked\b/.test(row[0]), 'the plugin is as it was').to.eq(original.enabled);
		});
	});

	it('keeps the message the administrator saves', function() {
		cy.get('button[id="plugins-button"]', {timeout: 60000}).click();
		waitJQuery();
		openSettings();

		// The same message in every language of the site, whichever the reader uses.
		cy.get(formSelector + ' input[name^="message["]').should('have.length.at.least', 1)
			.each(($input) => cy.wrap($input).invoke('val', message).trigger('input', {force: true}));
		cy.intercept({method: 'POST', url: '**/settings-plugin-grid/manage*'}).as('saved');
		waitFormHandler();
		cy.get(formSelector + ' button[type="submit"], ' + formSelector + ' button.submitFormButton').first().click();
		cy.wait('@saved', {timeout: 30000}).its('response.body.status').should('eq', true);
		cy.get(formSelector).should('not.exist');

		// Read back: the form comes again from the server.
		openSettings();
		cy.get(formSelector + ' input[name^="message["]').each(($input) => expect($input.val(), $input.attr('name')).to.eq(message));
		closeModal();
	});

	it('refuses a message longer than the band takes', function() {
		openSettings();
		cy.get(formSelector + ' input[name^="message["]').first().invoke('removeAttr', 'maxlength')
			.invoke('val', 'x'.repeat(201)).trigger('input', {force: true});
		cy.intercept({method: 'POST', url: '**/settings-plugin-grid/manage*'}).as('refused');
		waitFormHandler();
		cy.get(formSelector + ' button[type="submit"], ' + formSelector + ' button.submitFormButton').first().click();
		cy.wait('@refused', {timeout: 30000});
		cy.get(formSelector + ' .error, ' + formSelector + ' label.error, ' + formSelector + ' #formErrors', {timeout: 30000}).should('exist');
		closeModal();

		// The saved message did not change.
		get(gridUrl('manage') + '?verb=settings&plugin=' + rowName + '&category=generic').then((form) => {
			const doc = new DOMParser().parseFromString(form.content, 'text/html');
			doc.querySelectorAll('input[name^="message["]').forEach((input) => expect(input.value, input.name).to.eq(message));
		});
	});

	it('shows the message at the bottom of the journal pages', function() {
		cy.visit(pageUrl('') + '?reload=' + Date.now());
		band().should('eq', message);
		cy.window().then((win) => {
			const style = win.getComputedStyle(win.document.body, '::after');
			expect(style.position, 'fixed to the window').to.eq('fixed');
			expect(style.bottom).to.eq('0px');
		});
	});

	it('shows the message in the editorial back end', function() {
		cy.visit(pageUrl('submissions') + '?reload=' + Date.now());
		band().should('eq', message);
	});

	it('leaves a PDF galley viewer alone', function() {
		// The first PDF galley of the current issue, if the journal has one.
		request(pageUrl('issue/current')).then((issue) => {
			const article = /href="([^"]*\/article\/view\/[^"\/]+)"/.exec(issue.body);
			return article ? request(article[1]) : null;
		}).then((article) => {
			const galley = article && /<a[^>]*class="[^"]*obj_galley_link[^"]*pdf[^"]*"[^>]*href="([^"]+)"|<a[^>]*href="([^"]+)"[^>]*class="[^"]*obj_galley_link[^"]*pdf/.exec(article.body);
			if (!galley) {
				cy.log('this journal has no PDF galley in its current issue');
				return;
			}
			cy.visit((galley[1] || galley[2]).replace(/&amp;/g, '&'));
			cy.get('iframe', {timeout: 30000}).should('exist');
			band().should('eq', null);
		});
	});

	(managerUser ? it : it.skip)('cannot be switched off by a journal manager', function() {
		login(managerUser, managerPassword);
		cy.window({log: false}).then((win) => {
			const body = new URLSearchParams({plugin: rowName, category: 'generic', disableNotification: '1', csrfToken: win.pkp.currentUser.csrfToken});
			return cy.wrap(win.fetch(gridUrl('disable'), {method: 'POST', credentials: 'same-origin', body: body})
				.then((response) => response.text().then((text) => ({status: response.status, text: text}))));
		}).then((answer) => {
			expect(answer.text, 'the core refuses it').not.to.contain('"status":true');
		});
		cy.visit(pageUrl('') + '?reload=' + Date.now());
		band().should('eq', message);
		login(adminUser, adminPassword);
	});

	it('shows no band with the plugin off', function() {
		cy.visit(pageUrl('submissions') + '?reload=' + Date.now());
		post(gridUrl('disable'), {plugin: rowName, category: 'generic', disableNotification: 1}).then((answer) => {
			expect(answer, 'the plugin was switched off').to.contain('"status":true');
		});
		cy.visit(pageUrl('') + '?reload=' + Date.now());
		band().should('eq', null);
		cy.visit(pageUrl('submissions') + '?reload=' + Date.now());
		band().should('eq', null);
	});
});
