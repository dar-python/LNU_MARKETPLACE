# LNU Student Square Final Scope

Source basis: revised IT-105 title proposal in
`G:\APP-DEV-FILES\Revised_Title_Proposal_New_File.docx`.

## Project Identity

- Project title: `LNU Student Square`
- Course context: `IT-105 Mobile Development`
- Product positioning: an LNU-only moderated mobile platform for student
  services, creative or original commissions, and academic goods

## In Scope

- Access is limited to the LNU community.
- The platform supports only these listing domains:
  - student services
  - creative or original commissions
  - academic goods
- Allowed examples include tutoring, editing, design, commissions, repair,
  pre-loved textbooks, uniforms, and calculators.
- Accounts use Student ID, registered LNU email, and password.
- Password recovery and reset are email-based.
- Users can create, edit, delete, browse, search, filter, and manage listings
  within the approved categories.
- Listings support title, description, price, category, meetup or fulfillment
  arrangement, availability status, and photo uploads.
- Conditional listing fields are required as follows:
  - `Condition` (`New` or `Used`) for academic goods
  - `Service Type` for service listings
  - optional `Service Mode` (`Onsite`, `Remote`, or `Meetup`) for services
- Inquiry submission stays in scope as a form-based workflow only.
- Reporting stays in scope for prohibited, suspicious, scam, spam, or abusive
  listings and users.
- Admin moderation stays in scope, including fixed category management, report
  handling, moderation actions, dashboard metrics, and CSV or PDF export.

## Prohibited Content

- Food
- Beverages
- General retail items

The app must not be broadened back into a general campus marketplace.

## Inquiry and Moderation Rules

- No real-time chat is included in the initial version.
- Inquiry is handled through forms rather than live messaging.
- Admin handles moderation, including reviewing reports and disabling listings
  or users when needed.
- Categories are fixed and admin-managed rather than open-ended user-defined
  shop structures.

## Out of Scope for Initial Version

- Online payments
- Real-time chat
- Delivery or logistics coordination
- Multi-store shop profiles
- Ratings
- Reviews
- Profile, shop, or social-network style expansion beyond basic account access

## Implementation Guardrails

- Do not widen the product into a general-purpose marketplace.
- Do not add profile, shop, or social features outside the defined scope.
- Do not refactor working code unless the change is needed to align with this
  revised scope.
