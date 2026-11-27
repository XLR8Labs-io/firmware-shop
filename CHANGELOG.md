# Changelog

## 27 November 2026

- Customers have a wallet. Paid files can be bought from it: a confirmation shows the price, the balance and what is left, and the file's password appears once it is bought. If the balance is too low, the customer is told how much more they need. Nobody is charged a price they did not confirm.
- Customers can see their balance in the menu, their purchases (with file passwords) and their wallet history.
- Admins can look up customers, add or remove wallet money with a reason, and refund purchases. Every change is recorded with the admin who made it.
- Customers can browse the catalogue. The home page shows recent, top and featured files and the brands. Folder pages list their folders and files, sortable by newest, most downloaded or price. Each file has its own page with its size, checksum, price and flashing warnings. Search finds files by model name, model number or codename, and can be limited to one brand.
- Recent and top file lists, an RSS feed of new files and a sitemap for search engines are available.
- Admins can import a whole Google Drive folder into the catalogue: its folders become site folders and its files arrive as drafts at a chosen price. Running an import again only adds what is new, and the admin panel shows each import's progress.
- When adding a file by hand, pasting its Google Drive share link fills in the name, size and checksum.
- Admins can build the catalogue in the admin panel: folders for brands, series and models (with images, ordering and a visibility switch that also hides everything inside), and files with prices, passwords and their Google Drive details. Several files can be published, unpublished, featured, repriced or moved at once.
- The site now carries the IT-Firmware.com brand: its logo in the header, on the sign-in pages, in the account area and in the admin panel, plus its icons for browser tabs and phone home screens. Buttons and links use the brand blue.
- The admin Settings page now starts with the name the site is already using, so it no longer looks empty.
- Admins have an admin panel at `/admin`. It only opens for admin accounts that have two-factor sign-in turned on.
- Admins can change the site name, the scrolling notice bar, the support contact text and the Telegram, WhatsApp, Facebook and YouTube links from the panel. Changes show on the site straight away.
- Every page now has the store's header, notice bar and footer, with a disclaimer about flashing at your own risk.
- The project is set up. People can create an account, confirm their email address, and sign in with a password, a two-factor code or a passkey.
