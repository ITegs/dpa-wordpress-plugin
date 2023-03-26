# DPA Wordpress plugin

**Parse the press releases from the [presseportal.de](http://www.presseportal.de) and display them in your Wordpress blog automatically.**

_frequently read and set:_

- title
- content
- date
- tags
- image
- short url

## Installation

1. Upload the plugin using the Wordpress plugin uploader
2. Activate the plugin
3. Go to Settings > Presseportal Importer
4. Set the following settings:
   - Endpoint URL (e.g. https://api.presseportal.de/api/v2/stories/police)
   - Key
   - set the number of posts per fetch (default: 10)
   - set the fetch interval (default: 60 minutes)
   - set the article status (default: publish)
   - set the author-id (default: 1)
   - set activate
5. Save the settings

---

Based on the [dpa-digitalwires-wireq-wordpress-plugin](https://github.com/dpa-newslab/dpa-digitalwires-wireq-wordpress-plugin) by `dpa-newslab`.
