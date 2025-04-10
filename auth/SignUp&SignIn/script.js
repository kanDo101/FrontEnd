// Get all the sign up and sign in buttons (since you have multiple elements with the same IDs)
const signUpButtons = document.querySelectorAll('.signUpButton, #signUpButton');
const signInButtons = document.querySelectorAll('#signInButton');
const signInForm = document.getElementById('signin');
const signUpForm = document.getElementById('signup');

// Function to show sign up form
function showSignUp(e) {
    if(e) e.preventDefault(); // Prevent default link behavior
    signInForm.style.display = "none";
    signUpForm.style.display = "block";
}

// Function to show sign in form
function showSignIn(e) {
    if(e) e.preventDefault(); // Prevent default link behavior
    signInForm.style.display = "block";
    signUpForm.style.display = "none";
}

// Add event listeners to all sign up buttons
signUpButtons.forEach(button => {
    button.addEventListener('click', showSignUp);
});

// Add event listeners to all sign in buttons
signInButtons.forEach(button => {
    button.addEventListener('click', showSignIn);
});

// Set initial state (show sign in form by default)
window.addEventListener('DOMContentLoaded', function() {
    showSignIn();
});