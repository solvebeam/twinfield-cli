// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

export default defineConfig({
	site: 'https://cli.twinfield.solvebeam.com',
	integrations: [
		starlight({
			title: 'Twinfield CLI',
			description: 'Twinfield CLI brings Twinfield to your terminal. Free and open source.',
			logo: {
				src: './src/assets/brand/twinfield/twinfield-icon.svg',
				alt: 'Twinfield',
			},
			favicon: '/favicon.svg',
			social: [{ icon: 'github', label: 'GitHub', href: 'https://github.com/solvebeam/twinfield-cli' }],
			editLink: {
				baseUrl: 'https://github.com/solvebeam/twinfield-cli/edit/main/docs/',
			},
			lastUpdated: true,
			customCss: ['./src/styles/custom.css'],
			components: {
				Footer: './src/components/Footer.astro',
				PageTitle: './src/components/PageTitle.astro',
			},
			expressiveCode: {
				themes: ['github-dark-default', 'github-light-default'],
				styleOverrides: {
					borderRadius: '0.4rem',
				},
			},
			sidebar: [
				{
					label: 'Getting started',
					items: [
						{ label: 'Introduction', slug: '' },
						{ label: 'Profiles', slug: 'guides/profiles' },
						{ label: 'Your own Twinfield client', slug: 'guides/own-client' },
						{ label: 'Where the login is stored', slug: 'guides/credentials' },
						{ label: 'CI and environment variables', slug: 'guides/ci' },
						{ label: 'Scripting', slug: 'guides/scripting' },
					],
				},
				{
					// A page per command, named like the manual of the GitHub CLI: manual/twf_office_list.
					label: 'Manual',
					items: [
						{ label: 'twf', slug: 'manual/twf' },
						{
							label: 'twf deleted-transaction',
							items: [
								{ label: 'twf deleted-transaction', slug: 'manual/twf_deleted-transaction' },
								{ label: 'twf deleted-transaction list', slug: 'manual/twf_deleted-transaction_list' },
							],
						},
						{ label: 'twf login', slug: 'manual/twf_login' },
						{ label: 'twf logout', slug: 'manual/twf_logout' },
						{
							label: 'twf office',
							items: [
								{ label: 'twf office', slug: 'manual/twf_office' },
								{ label: 'twf office list', slug: 'manual/twf_office_list' },
							],
						},
						{ label: 'twf process-xml', slug: 'manual/twf_process-xml' },
						{ label: 'twf status', slug: 'manual/twf_status' },
						{ label: 'twf token', slug: 'manual/twf_token' },
					],
				},
			],
		}),
	],
});
