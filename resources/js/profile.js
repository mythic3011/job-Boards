function deleteProfileImage() {
    if (confirm('Are you sure you want to remove your profile image?')) {
        document.getElementById('delete-image-form').submit();
    }
}

window.deleteProfileImage = deleteProfileImage;
