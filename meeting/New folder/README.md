# Meeting Room Booking System

A simple, browser-based meeting room booking system using PHP and TXT files for storage.

## Features

- ✅ 6 Meeting Rooms (Meeting Room 1-4, Showroom 1-2)
- ✅ Browse available time slots (8:00 AM - 5:30 PM)
- ✅ 30-minute booking slots
- ✅ Check real-time availability
- ✅ Book rooms with name, date, and time
- ✅ Text file storage (no database required)
- ✅ Unique booking IDs
- ✅ Mobile-responsive design

## File Structure

```
meeting/
├── index.php              # Main web interface
├── css/
│   └── style.css          # Styling for the booking system
├── js/
│   └── app.js             # JavaScript functionality
├── api/
│   ├── get_bookings.php   # Fetch bookings for a date
│   └── create_booking.php # Create new booking
├── data/
│   ├── room1.txt          # Meeting Room 1 bookings
│   ├── room2.txt          # Meeting Room 2 bookings
│   ├── room3.txt          # Meeting Room 3 bookings
│   ├── room4.txt          # Meeting Room 4 bookings
│   ├── showroom1.txt      # Showroom 1 bookings
│   └── showroom2.txt      # Showroom 2 bookings
└── README.md              # This file
```

## How to Use

1. **Start WAMP64** - Make sure Apache and PHP are running
2. **Access the System** - Open your browser and go to:
   ```
   http://localhost/meeting/
   ```

3. **Select a Room** - Click on any of the 6 meeting rooms
4. **Choose a Date** - Pick the date you want to book (today or future dates)
5. **Select Time Slot** - Choose an available 30-minute time slot
6. **Enter Your Name** - Type your name
7. **Submit Booking** - Click "Book Now"
8. **Confirmation** - You'll receive a unique booking ID

## Data Storage Format

Each room has a TXT file in the `data/` folder. Bookings are stored in the format:

```
YYYY-MM-DD|HH:MM|Name|BookingID
2026-03-10|09:00|John Doe|BK20260310090001a2b3
2026-03-10|09:30|Jane Smith|BK20260310093002c4d5
2026-03-10|10:00|Bob Johnson|BK20260310100003e6f7
```

Each line represents one booking with:
- **Date**: YYYY-MM-DD format
- **Time**: HH:MM format (24-hour)
- **Name**: Person who booked
- **Booking ID**: Unique identifier

## Room Details

| Room ID | Room Name |
|---------|-----------|
| room1 | Meeting Room 1 |
| room2 | Meeting Room 2 |
| room3 | Meeting Room 3 |
| room4 | Meeting Room 4 |
| showroom1 | Showroom 1 |
| showroom2 | Showroom 2 |

## Operating Hours

- **Start Time**: 08:00 (8:00 AM)
- **End Time**: 17:30 (5:30 PM)
- **Slot Duration**: 30 minutes

## Managing Bookings

### View All Bookings
Open any TXT file in the `data/` folder to see all bookings for that room.

### Delete a Booking
1. Open the room's TXT file (e.g., `room1.txt`)
2. Find the booking line
3. Delete the entire line
4. Save the file

### Edit a Booking
1. Open the room's TXT file
2. Modify the name or time as needed
3. Keep the format: `YYYY-MM-DD|HH:MM|Name|BookingID`
4. Save the file

## Browser Compatibility

Works on:
- Chrome, Firefox, Safari, Edge
- Desktop and mobile devices
- Internet Explorer 11+

## Security Notes

- This is a simple public system - anyone can view and book
- No password protection
- TXT files are stored on the server
- For production use, consider adding authentication

## Troubleshooting

**Issue**: Data folder not writable
- **Solution**: Ensure the `data/` folder has write permissions (CHMOD 755 or 777)

**Issue**: Bookings not saving
- **Solution**: Check that PHP has permission to write to the `data/` folder

**Issue**: Page not loading
- **Solution**: Make sure Apache and PHP are running in WAMP64

## Modifications

To change room names, operating hours, or slot duration:

1. Edit `index.php` (lines 4-13):
   - Modify the `$rooms` array
   - Change `$operating_hours_start` and `$operating_hours_end`

2. For different slot duration (e.g., 1 hour instead of 30 min):
   - Edit `js/app.js` in the `generateTimeSlots()` function
   - Change `min += 30;` to `min += 60;`

## Support

For issues or questions, check the code comments in each file for detailed explanations.
