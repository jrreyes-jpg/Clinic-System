<div class="form-grid">
    <div class="form-group">
        <label for="fullname">Full Name</label>
        <input type="text" id="fullname" name="fullname" value="<?= e($patient['fullname'] ?? '') ?>" required>
    </div>

    <div class="form-group">
        <label for="birthdate">Birthdate</label>
        <input type="date" id="birthdate" name="birthdate" value="<?= e($patient['birthdate'] ?? '') ?>" required>
    </div>

    <div class="form-group">
        <label for="gender">Gender</label>
        <select id="gender" name="gender" required>
            <?php $selectedGender = $patient['gender'] ?? ''; ?>
            <option value="">Select gender</option>
            <option value="Male" <?= $selectedGender === 'Male' ? 'selected' : '' ?>>Male</option>
            <option value="Female" <?= $selectedGender === 'Female' ? 'selected' : '' ?>>Female</option>
            <option value="Other" <?= $selectedGender === 'Other' ? 'selected' : '' ?>>Other</option>
        </select>
    </div>

    <div class="form-group">
        <label for="contact_number">Contact Number</label>
        <input type="text" id="contact_number" name="contact_number" value="<?= e($patient['contact_number'] ?? '') ?>" required>
    </div>

    <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($patient['email'] ?? '') ?>">
    </div>

    <div class="form-group form-group-wide">
        <label for="address">Address</label>
        <input type="text" id="address" name="address" value="<?= e($patient['address'] ?? '') ?>">
    </div>
</div>
