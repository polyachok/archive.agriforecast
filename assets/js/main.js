document.addEventListener("DOMContentLoaded", () => {
  console.log("main.js loaded")

  const deleteButtons = document.querySelectorAll('button[name="delete"]')
  deleteButtons.forEach((button) => {
    button.addEventListener("click", (e) => {
      if (!confirm("Вы уверены, что хотите удалить этот элемент?")) {
        e.preventDefault()
      }
    })
  })

  const changePasswordForm = document.querySelector('form[name="change_password"]')
  if (changePasswordForm) {
    changePasswordForm.addEventListener("submit", (e) => {
      const newPassword = document.getElementById("new_password").value
      const confirmPassword = document.getElementById("confirm_password").value

      if (newPassword !== confirmPassword) {
        alert("Новый пароль и подтверждение не совпадают")
        e.preventDefault()
      }
    })
  }
})

if (typeof window.openPasswordModal === "undefined") {
  window.openPasswordModal = (userId) => {
    console.log("Global openPasswordModal called for user ID:", userId)
    const passwordModal = document.getElementById("passwordModal")
    if (passwordModal) {
      document.getElementById("password_user_id").value = userId
      passwordModal.classList.add("active")
    } else {
      console.error("Password modal not found in global function")
    }
  }
}

if (typeof window.closePasswordModal === "undefined") {
  window.closePasswordModal = () => {
    console.log("Global closePasswordModal called")
    const passwordModal = document.getElementById("passwordModal")
    if (passwordModal) {
      passwordModal.classList.remove("active")
    }
  }
}

if (typeof window.submitPasswordForm === "undefined") {
  window.submitPasswordForm = () => {
    console.log("Global submitPasswordForm called")
    const form = document.getElementById("passwordForm")
    if (!form) {
      console.error("Password form not found in global function")
      return
    }

    const newPassword = document.getElementById("new_password").value
    const confirmPassword = document.getElementById("confirm_password").value

    if (newPassword !== confirmPassword) {
      alert("Пароли не совпадают")
      return
    }

    const formData = new FormData(form)
    formData.append("change_password", "1")

    fetch(window.location.pathname, {
      method: "POST",
      headers: {
        "X-Requested-With": "XMLHttpRequest",
      },
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          window.closePasswordModal()
          window.showMessage("success", data.message)
        } else {
          window.showMessage("error", data.message || "Произошла ошибка")
        }
      })
      .catch((error) => {
        console.error("Error:", error)
        window.showMessage("error", "Ошибка сети")
      })
  }
}

if (typeof window.showMessage === "undefined") {
  window.showMessage = (type, text) => {
    console.log("Global showMessage called:", type, text)
    const messageDiv = document.createElement("div")
    messageDiv.className = `toast toast-${type}`
    messageDiv.textContent = text

    document.body.appendChild(messageDiv)

    setTimeout(() => {
      messageDiv.remove()
    }, 3000)
  }
}
