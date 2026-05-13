// Initialize date picker with tomorrow's date as minimum
document.addEventListener('DOMContentLoaded', function() {
    setMinDate();
    setupRoomButtons();
    setupBookingForm();
    setupDeleteModal();
});

function setMinDate() {
    const dateInput = document.getElementById('booking-date');
    const today = new Date();
    today.setDate(today.getDate());
    const minDate = today.toISOString().split('T')[0];
    dateInput.setAttribute('min', minDate);
}

function setupRoomButtons() {
    const roomBtns = document.querySelectorAll('.room-btn');
    roomBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            selectRoom(this.dataset.roomId);
        });
    });
}

function selectRoom(roomId) {
    selectedRoomId = roomId;
    
    // Update active button
    document.querySelectorAll('.room-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.closest('.room-btn').classList.add('active');
    
    // Show room info
    document.getElementById('room-info').classList.remove('hidden');
    document.getElementById('no-room-selected').classList.add('hidden');
    
    const roomName = rooms[roomId];
    document.getElementById('selected-room-name').textContent = roomName;
    
    // Load availability for today
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('booking-date').value = today;
    loadAvailability(today);
    populateStartTimes(today);
}

function setupBookingForm() {
    document.getElementById('booking-date').addEventListener('change', function() {
        if (selectedRoomId) {
            loadAvailability(this.value);
            populateStartTimes(this.value);
            resetEndTime();
        }
    });

    document.getElementById('booking-start-time').addEventListener('change', function() {
        if (selectedRoomId && document.getElementById('booking-date').value) {
            populateEndTimes(this.value, document.getElementById('booking-date').value);
        }
    });

    document.getElementById('submit-booking').addEventListener('click', function() {
        submitBooking();
    });
}

function populateStartTimes(selectedDate) {
    const startSelect = document.getElementById('booking-start-time');
    startSelect.innerHTML = '<option value="">-- Select start time --</option>';
    
    const slots = generateTimeSlots();
    slots.forEach(time => {
        const option = document.createElement('option');
        option.value = time;
        option.textContent = time;
        startSelect.appendChild(option);
    });
}

function populateEndTimes(startTime, selectedDate) {
    const endSelect = document.getElementById('booking-end-time');
    endSelect.innerHTML = '<option value="">-- Select end time --</option>';
    
    const slots = generateTimeSlots();
    const startIndex = slots.indexOf(startTime);
    
    if (startIndex === -1) return;
    
    // Show end times after start time
    for (let i = startIndex + 1; i < slots.length; i++) {
        const time = slots[i];
        const option = document.createElement('option');
        option.value = time;
        option.textContent = time;
        endSelect.appendChild(option);
    }
}

function resetEndTime() {
    document.getElementById('booking-end-time').innerHTML = '<option value="">-- Select end time --</option>';
}

function generateTimeSlots() {
    const slots = [];
    const [startHour, startMin] = operatingStart.split(':').map(Number);
    const [endHour, endMin] = operatingEnd.split(':').map(Number);
    
    let hour = startHour;
    let min = startMin;
    
    while (hour < endHour || (hour === endHour && min < endMin)) {
        const timeStr = String(hour).padStart(2, '0') + ':' + String(min).padStart(2, '0');
        slots.push(timeStr);
        
        // Add 30 minutes
        min += 30;
        if (min >= 60) {
            min -= 60;
            hour += 1;
        }
    }
    
    return slots;
}


function loadAvailability(selectedDate) {
    if (!selectedRoomId) return;
    
    fetch('api/get_bookings.php?room_id=' + selectedRoomId + '&date=' + selectedDate)
        .then(response => response.json())
        .then(data => {
            displayAvailability(data, selectedDate);
        })
        .catch(error => {
            console.error('Error:', error);
        });
}

function displayAvailability(bookings, selectedDate) {
    const availList = document.getElementById('availability-list');
    availList.innerHTML = '';
    
    const dateObj = new Date(selectedDate);
    const dateStr = dateObj.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    document.getElementById('availability-date').textContent = dateStr;
    
    if (bookings.length === 0) {
        availList.innerHTML = '<p style="padding: 15px; text-align: center; color: #666;">No bookings for this date. Room is fully available!</p>';
        return;
    }
    
    bookings.forEach(booking => {
        const slot = document.createElement('div');
        slot.className = 'time-slot booked';
        slot.innerHTML = booking.start_time + ' - ' + booking.end_time + ' - Booked by ' + booking.name + 
                         '<button class="delete-btn" data-booking-id="' + booking.booking_id + '" title="Delete booking">×</button>';
        
        // Add delete click handler
        const deleteBtn = slot.querySelector('.delete-btn');
        console.log('Delete button found:', !!deleteBtn, 'Booking ID:', booking.booking_id);
        
        deleteBtn.addEventListener('click', function(e) {
            console.log('Delete button clicked for booking:', booking.booking_id);
            e.stopPropagation();
            e.preventDefault();
            openDeleteModal(booking.booking_id, booking.start_time, booking.end_time, booking.name);
        });
        
        availList.appendChild(slot);
    });
}

function submitBooking() {
    const name = document.getElementById('booking-name').value.trim();
    const date = document.getElementById('booking-date').value;
    const startTime = document.getElementById('booking-start-time').value;
    const endTime = document.getElementById('booking-end-time').value;
    const messageDiv = document.getElementById('booking-message');
    
    // Validation
    if (!name) {
        showMessage('Please enter your name', 'error', messageDiv);
        return;
    }
    
    if (!date) {
        showMessage('Please select a date', 'error', messageDiv);
        return;
    }
    
    if (!startTime) {
        showMessage('Please select a start time', 'error', messageDiv);
        return;
    }
    
    if (!endTime) {
        showMessage('Please select an end time', 'error', messageDiv);
        return;
    }
    
    if (startTime >= endTime) {
        showMessage('End time must be after start time', 'error', messageDiv);
        return;
    }
    
    // Submit booking
    const formData = new FormData();
    formData.append('room_id', selectedRoomId);
    formData.append('name', name);
    formData.append('date', date);
    formData.append('start_time', startTime);
    formData.append('end_time', endTime);
    
    fetch('api/create_booking.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('Room booked successfully! Booking ID: ' + data.booking_id, 'success', messageDiv);
            
            // Reset form
            document.getElementById('booking-name').value = '';
            document.getElementById('booking-start-time').value = '';
            document.getElementById('booking-end-time').value = '';
            
            // Reload availability
            loadAvailability(date);
            populateStartTimes(date);
            resetEndTime();
        } else {
            showMessage('Error: ' + (data.message || 'Could not book room'), 'error', messageDiv);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Error: ' + error.message, 'error', messageDiv);
    });
}

function showMessage(text, type, element) {
    element.textContent = text;
    element.className = 'message ' + type;
    element.classList.remove('hidden');
    
    // Auto hide after 5 seconds
    setTimeout(() => {
        element.classList.add('hidden');
    }, 5000);
}

function setupDeleteModal() {
    const modal = document.getElementById('delete-modal');
    const closeBtn = document.querySelector('.close-modal');
    const cancelBtn = document.getElementById('cancel-delete');
    const confirmBtn = document.getElementById('confirm-delete');
    const passwordInput = document.getElementById('delete-password');
    
    console.log('setupDeleteModal called');
    console.log('Modal found:', !!modal);
    console.log('Close button found:', !!closeBtn);
    
    if (!modal || !closeBtn || !cancelBtn || !confirmBtn || !passwordInput) {
        console.error('Delete modal elements not found!');
        return;
    }
    
    closeBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        closeDeleteModal();
    });
    
    cancelBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        closeDeleteModal();
    });
    
    modal.addEventListener('click', function(event) {
        if (event.target === modal) {
            closeDeleteModal();
        }
    });
    
    confirmBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        confirmDelete();
    });
    
    passwordInput.addEventListener('keypress', function(event) {
        if (event.key === 'Enter') {
            confirmDelete();
        }
    });
}

let currentDeleteBookingId = null;
let currentDeleteRoomId = null;

function openDeleteModal(bookingId, startTime, endTime, name) {
    console.log('openDeleteModal called with booking:', bookingId);
    
    currentDeleteBookingId = bookingId;
    currentDeleteRoomId = selectedRoomId;
    const modal = document.getElementById('delete-modal');
    const passwordInput = document.getElementById('delete-password');
    const deleteMessage = document.getElementById('delete-message');
    
    console.log('Modal:', !!modal, 'Input:', !!passwordInput, 'Message:', !!deleteMessage);
    
    if (!modal) {
        console.error('Modal element not found!');
        return;
    }
    
    passwordInput.value = '';
    deleteMessage.classList.add('hidden');
    modal.classList.remove('hidden');
    console.log('Modal classes after remove hidden:', modal.className);
    passwordInput.focus();
}

function closeDeleteModal() {
    const modal = document.getElementById('delete-modal');
    const passwordInput = document.getElementById('delete-password');
    const deleteMessage = document.getElementById('delete-message');
    
    passwordInput.value = '';
    deleteMessage.classList.add('hidden');
    modal.classList.add('hidden');
    currentDeleteBookingId = null;
    currentDeleteRoomId = null;
}

function confirmDelete() {
    const password = document.getElementById('delete-password').value;
    const deleteMessage = document.getElementById('delete-message');
    
    if (!password) {
        showDeleteMessage('Please enter the password', 'error', deleteMessage);
        return;
    }
    
    if (!currentDeleteBookingId || !currentDeleteRoomId) {
        showDeleteMessage('Error: No booking selected', 'error', deleteMessage);
        return;
    }
    
    const formData = new FormData();
    formData.append('booking_id', currentDeleteBookingId);
    formData.append('room_id', currentDeleteRoomId);
    formData.append('password', password);
    
    fetch('api/delete_booking.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showDeleteMessage('Booking deleted successfully!', 'success', deleteMessage);
            setTimeout(() => {
                closeDeleteModal();
                const currentDate = document.getElementById('booking-date').value;
                loadAvailability(currentDate);
            }, 1500);
        } else {
            showDeleteMessage('Error: ' + (data.message || 'Could not delete booking'), 'error', deleteMessage);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showDeleteMessage('Error: ' + error.message, 'error', deleteMessage);
    });
}

function showDeleteMessage(text, type, element) {
    element.textContent = text;
    element.className = 'message ' + type;
    element.classList.remove('hidden');
}
