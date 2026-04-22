# Member Photo Submission with Moderation Queue

## Summary

This PR adds a self-service photo submission workflow so members can contribute photos to event albums, with a review step before they go live.

## New features:
- Photo submission – Any logged-in member can submit one or more photos to an event album via a consent-gated upload form. Multiple files can be selected in a single submission; each file is validated (JPEG/PNG/GIF, max 20 MB).
- Moderation queue – Submissions land in a pending queue. PhotoCee and Board members see a banner on the album page when submissions are awaiting review, and can approve (moves the file into the album) or reject (deletes the file) from a dedicated review page.
- Auto-approval – When a PhotoCee or Board member submits photos themselves, they are approved and added to the album immediately, bypassing the queue.
- Auto-created event albums – When a calendar event is created or its proposal accepted, an album is automatically created for it. Albums are nested inside an academic-year parent album ("Photos from YYYY/YYYY+1", created on demand). The submission prompt is hidden on year albums.

## Database changes (migrations/schema_55_member_photo_submissions.sql):
- Adds agenda_id FK column to foto_boeken to link albums to events.
- Adds foto_submissions table tracking file path, submitter, status (pending/approved/rejected), and reviewer.

## New files:
- src/DataIter/DataIterPhotoSubmission.php
- src/DataModel/DataModelPhotoSubmission.php
- src/Form/PhotoSubmissionType.php
- src/Controller/PhotoSubmissionsController.php
- templates/photos/submissions/submit.html.twig
- templates/photos/submissions/review.html.twig

## Modified files:
- DataIterPhotobook – added agenda_id field
- DataModelPhotobook – added get_book_for_event(), create_for_event(), get_or_create_year_book()
- DataModelAgenda – auto-creates album on event insert and proposal accept
- PolicyPhotobook – added userCanSubmitPhotos() and userCanReviewSubmissions()
- PhotoBooksController – exposes pending submission count to the album view
- templates/photos/books/single.html.twig – submission prompt and review banner

## Test plan
- Create a calendar event and confirm an album is auto-created under the correct academic year album (created if missing)
- As a regular member, open an event album and submit one or more photos, confirm they appear in the review queue
- As PhotoCee/Board, approve a submission and confirm the photo appears in the album; reject one and confirm it is removed
- As PhotoCee/Board, submit photos directly and confirm they are added to the album immediately without going through the queue
- Confirm the submission prompt does not appear on albums with no event ID (such as "Photos from YYYY/YYYY+1")
- Confirm submitting a non-image file is rejected with a validation error
- Run the migration on a clean copy of the database: psql <db> < migrations/schema_55_member_photo_submissions.sql
