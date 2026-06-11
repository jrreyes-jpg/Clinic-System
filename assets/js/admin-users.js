const editDialog = document.querySelector('#editUserDialog');
const resetDialog = document.querySelector('#resetPasswordDialog');
const userSearch = document.querySelector('#userSearch');
const usersTable = document.querySelector('#usersTable');

function openDialog(dialog) {
    if (!dialog) {
        return;
    }

    if (typeof dialog.showModal === 'function') {
        dialog.showModal();
        return;
    }

    dialog.setAttribute('open', '');
}

function closeDialog(button) {
    const dialog = button.closest('dialog');

    if (dialog) {
        dialog.close();
    }
}

document.querySelectorAll('.js-edit-user').forEach((button) => {
    button.addEventListener('click', () => {
        document.querySelector('#editUserId').value = button.dataset.id || '';
        document.querySelector('#editFullname').value = button.dataset.fullname || '';
        document.querySelector('#editUsername').value = button.dataset.username || '';
        document.querySelector('#editEmail').value = button.dataset.email || '';
        document.querySelector('#editMobile').value = button.dataset.mobile || '';

        openDialog(editDialog);
    });
});

document.querySelectorAll('.js-reset-user').forEach((button) => {
    button.addEventListener('click', () => {
        document.querySelector('#resetUserId').value = button.dataset.id || '';
        document.querySelector('#resetUserName').textContent = button.dataset.name || '';
        document.querySelector('#resetPassword').value = '';

        openDialog(resetDialog);
    });
});

document.querySelectorAll('.js-close-dialog').forEach((button) => {
    button.addEventListener('click', () => closeDialog(button));
});

document.querySelectorAll('.app-dialog').forEach((dialog) => {
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });
});

if (userSearch && usersTable) {
    userSearch.addEventListener('input', () => {
        const query = userSearch.value.trim().toLowerCase();

        usersTable.querySelectorAll('tbody tr').forEach((row) => {
            const text = row.textContent.toLowerCase();
            row.hidden = query !== '' && !text.includes(query);
        });
    });
}
