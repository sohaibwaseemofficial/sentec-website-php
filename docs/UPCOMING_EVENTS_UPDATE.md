# Upcoming Events Feature Update

## Changes Made

### 1. Database Schema Update
- **New Column**: Added `event_link` column to the `events` table
- **Location**: `admin/add_event_link_column.sql`
- **Action Required**: Run the SQL migration file in phpMyAdmin or MySQL console

```sql
ALTER TABLE `events` ADD COLUMN `event_link` VARCHAR(500) DEFAULT NULL AFTER `image_url`;
```

### 2. Fixed Image Display Ratio
- **Issue**: Images with 1080x1350px (portrait) ratio were being cropped
- **Solution**: Updated CSS to use `aspect-ratio` property and `object-fit: contain`
- **Result**: Images now display fully without cropping, maintaining the proper portrait ratio

**Changes in `css/style.css`:**
- Added `.event-image-wrapper` with aspect ratio 1080/1350
- Changed image `object-fit` from `cover` to `contain`
- Updated mobile responsive styles to maintain aspect ratio

### 3. Made Event Cards Clickable
- **Feature**: Event cards are now clickable links
- **Behavior**: 
  - If an event has a link, clicking the card opens that URL in a new tab
  - If no link is provided, the card remains non-functional
- **File Modified**: `index.php`

### 4. Admin Panel Updates

#### Add Event Form (`admin/add_event.php`)
- Added "Event Link" input field (optional)
- Field accepts URL format
- Updated database insert query to include `event_link`

#### Edit Event Form (`admin/edit_event.php`)
- Added "Event Link" input field (optional)
- Field is pre-filled with existing link value
- Updated database update query to include `event_link`

## How to Use

### For Administrators:

1. **Run Database Migration**:
   - Open phpMyAdmin
   - Select your database
   - Go to SQL tab
   - Copy and paste the content from `admin/add_event_link_column.sql`
   - Click "Go" to execute

2. **Adding New Events**:
   - Go to Admin Panel → Add Event
   - Fill in all required fields
   - Upload a 1080x1350px image for best results
   - (Optional) Add an event link in the "Event Link" field
   - Submit the form

3. **Editing Existing Events**:
   - Go to Admin Panel → Manage Events
   - Click "Edit" on any event
   - Update the "Event Link" field as needed
   - Save changes

### For Users:

- Visit the homepage to see upcoming events
- Events with links are clickable and will open in a new tab
- Images now display in their full portrait format without cropping

## Technical Details

### CSS Changes
- Portrait aspect ratio: `aspect-ratio: 1080 / 1350`
- Image display: `object-fit: contain` (shows full image)
- Hover effect: Cards lift and change border color
- Responsive: Maintains aspect ratio on all screen sizes

### Database Schema
```sql
event_link VARCHAR(500) DEFAULT NULL
```

### Security
- All URLs are sanitized using `htmlspecialchars()`
- Links open in new tabs with `rel="noopener noreferrer"` for security
- URL validation on the input field

## Recommended Image Specifications

- **Dimensions**: 1080 x 1350 pixels
- **Aspect Ratio**: 4:5 (portrait)
- **Format**: JPG, PNG, or WEBP
- **File Size**: Under 2MB recommended for performance

## Browser Compatibility

- Modern browsers (Chrome, Firefox, Safari, Edge)
- Mobile responsive
- Supports touch interactions on mobile devices
