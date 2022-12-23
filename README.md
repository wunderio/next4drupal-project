## Next.js for Drupal template by Wunder

This is a starter template for a decoupled website using the open-source [Next.js for Drupal](https://next-drupal.org/) 
by [Chapter Three](https://www.chapterthree.com) and contributors.

It includes the codebase for both the Drupal backend and the corresponding Next.js frontend. 
Some example content types and languages are included in the configuration to provide a working multilingual example out of the box, using the upcoming
[Distributions and Recipes](https://www.drupal.org/about/core/strategic-initiatives-distributions-and-recipes) Drupal core initiative.

This example is meant to be used together with the [Silta](https://wunderio.github.io/silta/) hosting system by [Wunder](https://www.wunder.io), but it can be used with any hosting system.

### Lando setup

Local development is handled by [Lando](https://lando.dev/). Both frontend and backend are covered by the Lando setup,
so that is the only real requirement. The frontend site can be run in either dev or prod mode, 
and it will be proxied by lando at the configured url in lando. The default is [https://frontend.lndo.site](https://frontend.lndo.site)"

### Getting started

Follow this guide to get backend and frontend up and running.

#### Drupal setup

All drupal code and configuration is in the `drupal` directory.
All needed module dependencies have been added to the `composer.json` file.
Part of the setup process is automated using [drupal recipes](https://www.drupal.org/project/distributions_recipes).

Follow these steps to get started:

1. `lando start`
2. `lando composer install`
3. Generate oauth keys using the command `lando generate-oauth-keys`. The keys will be created in the `drupal/oauth` directory.
4. Install Drupal as usual. Use the standard installation profile. You can do it via the UI or using this command: `lando drush si --site-name="My great site name here"`.
5. Run the `lando install-recipe wunder_next_setup` to set up all necessary modules, content types and configuration.
6. Run `lando drush eshs` to set up elasticsearch indexes.
6. You can now export your drupal configuration the usual way: `lando drush cex`.

#### Next.js setup

All next.js code  is in the `next` directory.

For frontend development, prefix npm commands with `lando`, so for example to start the
local node server in development mode, you can use `lando npm run dev`. You can still use your local node server, if you prefer.

Follow these steps to get started:

1. Execute the command: `lando drush wunder_next:setup-user-and-consumer` and copy the output somewhere safe.
2. Move to the next directory: `cd next`
3. Make a copy of the example .env file for local use: `cp .env.example .env.local`
4. Add the output of the command in step 1 to the end of your newly created `.env.local` file and save it.
5. `lando npm install`
6. `lando npm run dev`
7. Visit `https://frontend.lndo.site` and you should see your content displayed by the frontend.
8. When viewing a piece of content inside Drupal, you should be able to preview it in the frontend, including unpublished content and revisions.
9. The template includes automatic setup of [On demand revalidation](https://next-drupal.org/learn/on-demand-revalidation), so saving a piece of content will automatically revalidate the corresponding path in next.js.

> NOTE: If you get ar error message saying `https://next4drupal-project.lndo.site/jsonapi failed, reason: unable to verify the first certificate`,
decomment the `NODE_TLS_REJECT_UNAUTHORIZED=0` line in .env.local