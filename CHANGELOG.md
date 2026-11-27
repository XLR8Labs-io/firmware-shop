# Changelog

## 27 November 2026

- Admins can ban a customer with a reason. A banned customer can still sign in and see their wallet, but cannot buy or download files, and download links they already have stop working. They see the reason on their dashboard and on file pages. Bans can be lifted.
- The database is backed up every night, and two weeks of backups are kept. If a backup fails, the admins get a Telegram message.
- If something goes wrong on the live site, the admins get a Telegram message about it, at most once an hour for the same problem.
- Pages now tell browsers not to let other sites embed them, which protects against click-tricking attacks.
- Customers can pay with bKash or Nagad online. They enter the amount, pay on the store's payment page, and the money is in their wallet as soon as the payment is confirmed, with no waiting for an admin. Sending money and entering the TrxID by hand still works as before.
- A payment is never credited twice, whether it comes in online, by hand, or both. Online payments that don't match what was ordered are held for an admin, who gets a Telegram alert.
- The store can post announcements. They appear on the home page and on an Announcements page, and can also scroll in the notice bar at the top of every page. An announcement can be scheduled for a later time.
- The site has a blog, with cover images, and pages such as About us, Terms or How to buy. Published pages are linked at the bottom of every page. Everything is written and published from the admin panel.
- Visitors can ask for a file the store doesn't have yet. Signed-in customers see their requests and the store's replies on the same page. Admins see open requests, with a count in the menu, and can reply to them.
- A World agents page lists local agents and how to contact them. An agent linked to an account can send their wallet balance to a customer by email address, after checking the customer's name.
- Customers can add money with USDT on TRON (TRC20) or BNB Smart Chain (BEP20). Each deposit gets its own exact amount to send, with the store's address and a QR code. TRON payments are found within a minute or two. For BNB Smart Chain, the customer pastes their transaction hash. The page updates on its own, and the customer gets an email once the money is in their wallet.
- A payment is only ever credited to the customer whose deposit it matches. Copying someone else's transaction hash gets nothing, and fake USDT tokens are ignored. A payment that arrives up to a day late still counts.
- Payments that match no deposit, for example because too little was sent, go to a review list in the admin panel and trigger a Telegram alert. An admin can credit one to the right customer with a reason.
- Customers can add money with bKash or Nagad: the Add funds page shows the store's number, what to choose in the app, today's rate and how many dollars they will receive. They enter the amount, their number and the TrxID, and see each deposit's status. The Buy box links there with the missing amount filled in.
- Admins check deposits in a queue (with a count in the menu), can correct the amount, and approve or reject with a reason. The customer gets an email either way, and admins get a Telegram alert for each new deposit. The rate, numbers, account types and minimum are set on the Settings page.
- Customers can download free files and files they bought. Downloads come through a short-lived link that also works in download managers and can be resumed. Each link works from only a few places, and each customer has a daily download limit; admins can change both.
- If Google Drive temporarily blocks a popular file, its mirror copy is used. If there is none, the customer is told to try again later and admins get a Telegram alert.
- Admins can see every download, filter them by customer or file, and spot links used from more than one place. The dashboard shows how much was downloaded this month.
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
