HTMLElement.prototype.offset = function() {
  let offsetLeft = this.offsetLeft - this.scrollLeft + window.scrollX,
    offsetTop = this.offsetTop - this.scrollTop + window.scrollY,
    parentObj = this.offsetParent;
  while (parentObj != null) {
    offsetLeft += parentObj.offsetLeft - parentObj.scrollLeft;
    offsetTop += parentObj.offsetTop - parentObj.scrollTop;
    parentObj = parentObj.offsetParent;
  }
  return {'left': offsetLeft, 'top': offsetTop};
}

class ISLE {

  static appendFetch(queryString) {
    return this.fetchHtml(queryString).then(element => {
      document.body.append(element);
      return element;
    });
  }

  static addEventListeners(parentElement) {

    parentElement.querySelectorAll('.needs-validation').forEach(element => {
      element.addEventListener('submit', event => {
        if (!element.checkValidity()) {
          event.preventDefault();
          event.stopPropagation();
        }
        element.classList.add('was-validated');
      }, false);
    });

    parentElement.querySelectorAll('.tagsinput').forEach(element => {
      element.addEventListener('click', function(event) {
        if (this == event.target) {
          element.querySelector('[data-target="tag"]').focus();
        }
      });
    });
    parentElement.querySelectorAll('.tagsinput .tag').forEach(element => {
      element.addEventListener('change', function(event) {
        const source = element.closest('.tagsinput').querySelector('[data-target="tag"]');
        const tag = document.createElement('span');
        tag.classList.add('badge', 'bg-primary');
        tag.innerHTML = source.value + `<span data-role="remove"></span>`;
        tag.querySelector('[data-role="remove"]').addEventListener('click', event => {
          tag.remove();
        });
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'categories[]';
        input.value = this.value;
        tag.append(input);
        source.value = '';
        source.insertAdjacentElement('beforebegin', tag);
        source.focus();
      });
    });
    parentElement.querySelectorAll('.tagsinput [data-role="remove"]').forEach(element => {
      element.addEventListener('click', event => {
        element.closest('.badge').remove();
      });
    });

    // File uploader with spinner control
    parentElement.querySelectorAll('.form-floating input[type="file"][data-upload]').forEach(element => {
      element.addEventListener('change', event => {
        if (element.files.length != 1) {
          return;
        }
        const data = new FormData();
        data.append('file', element.files[0]);
        element.nextElementSibling.classList.remove('d-none'); // Show spinner
        ISLE.fetch('method=upload', {
          method: 'POST',
          body: data
        }).then(data => {
          element.nextElementSibling.classList.add('d-none'); // Hide spinner
          if (element.dataset.upload == 'image') {
            const imageContainer = element.closest('.form-floating').querySelector('[data-container="image"]');
            const imagePath = window['web_root'] + '/uploads/images/' + data.hash + '.jpg';
            fetch(imagePath).then(response => {
                if (!response.ok) {
                  throw response.statusText;
                }
                imageContainer.querySelector('img').src = window['web_root'] + '/uploads/images/' + data.hash + '.jpg';
                imageContainer.querySelector('[name="image"]').value = data.hash;
                imageContainer.classList.remove('d-none');
            }).catch(error => {
              return ISLE.error('File is not an image.');
            });
          } else if (element.dataset.upload == "multiple") {
            const newFileDiv = ISLE.template(`<div class="input-group my-1">
              <input type="hidden" name="${element.dataset.target || 'files[]'}" value="${data.hash}">
              <input type="text" class="form-control" value="${element.files[0].name}" disabled>
              <div class="btn btn-danger" data-role="remove"><i class="bi-x-lg"></i></div>
            </div>`);
            newFileDiv.querySelector('[data-role="remove"]').addEventListener('click', event => {
                newFileDiv.remove();
            });
            element.closest('.form-floating').querySelector('[data-container="file"]').append(newFileDiv);
            element.value = "";
          }
        });
      });
    });

    parentElement.querySelectorAll('.form-floating [data-container="attribute"]~.input-group [data-role="add"]').forEach(element => {
      element.addEventListener('click', event => {
        const inputGroup = element.closest('.input-group');
        const attributeId = inputGroup.querySelector('[name="attributes[]"]');
        const attributeName = inputGroup.querySelector('[name="attribute_names[]"]');
        const attributeValue = inputGroup.querySelector('[name="attribute_values[]"]');
        if (!attributeId.value || !attributeValue.value) {
          inputGroup.classList.add('is-invalid');
          return;
        }
        inputGroup.classList.remove('is-invalid');
        const newAttribute = ISLE.template(`<div class="input-group my-1">
          <input type="hidden" name="attributes[]" value="${attributeId.value}">
          <input type="text" class="form-control" name="attribute_names[]" value="${attributeName.value}">
          <input type="text" class="form-control" name="attribute_values[]" value="${attributeValue.value}">
          <div class="btn btn-danger" data-role="remove"><i class="bi-x-lg"></i></div>
        </div>`);
        newAttribute.querySelector('[data-role="remove"]').addEventListener('click', event => {
          newAttribute.remove();
        });
        const attributeContainer = element.closest('.form-floating').querySelector('[data-container="attribute"]');
        attributeContainer.append(newAttribute);
        attributeId.value = '';
        attributeName.value = '';
        attributeValue.value = '';
      });
    });

    parentElement.querySelectorAll('select+[data-role="clear"]').forEach(element => {
      const select = element.previousElementSibling;
      select.addEventListener('change', event => {
        if (select.selectedOptions.length == 0) {
          element.classList.add('d-none');
        } else {
          element.classList.remove('d-none');
        }
      });
      element.addEventListener('click', event => {
        Array.from(select.selectedOptions).forEach(option => {
          option.selected = false;
        });
        element.classList.add('d-none');
      });
    });

    parentElement.querySelectorAll('[data-autocomplete]').forEach(this.autocomplete);

    parentElement.querySelectorAll('[data-form]').forEach(element => {
      element.addEventListener('click', event => {
        ISLE.form(element.dataset.form);
      });
    });

    parentElement.querySelectorAll('[data-cart]:not([data-role])').forEach(element => {
      element.addEventListener('click', event => {
        ISLE.showCart(element.dataset.cart);
      });
    });

    parentElement.querySelectorAll('[data-container="image"]').forEach(element => {
      element.querySelector('[data-role="remove"]').addEventListener('click', event => {
        element.querySelector('[name="image"]').value = "";
        element.classList.add('d-none');
      });
    });

    parentElement.querySelectorAll('[data-container] .input-group [data-role="remove"]').forEach(element => {
      element.addEventListener('click', event => {
        element.closest('.input-group').remove();
      });
    });

    parentElement.querySelectorAll('[data-id] [data-transact], [data-id][data-transact]').forEach(element => {
      element.addEventListener('click', event => {
        const id = element.closest('[data-id]').dataset.id;
        const type = element.dataset.transact;
        ISLE.form(`transactionForm&type=${type}&asset=${id}`);
      });
    });

  }

  static autocomplete(element) {
    const INITIAL_LOOKUP = true;
    const cache = {};
    element.parentNode.classList.add('dropdown');
    element.setAttribute('data-bs-toggle', 'dropdown');
    element.classList.add('dropdown-toggle');
    const dropdownElement = ISLE.template(`<div class="dropdown-menu"></div>`);
    element.after(dropdownElement);
    const dropdown = new bootstrap.Dropdown(element);
    element.addEventListener('click', event => {
      if (dropdownElement.childElementCount == 0) {
        if (INITIAL_LOOKUP) {
          element.dispatchEvent(new Event('input'));
        } else {
          event.stopPropagation();
          dropdown.hide();
        }
      }
    });
    element.addEventListener('input', event => {
      if (element.value.length == 0 && !INITIAL_LOOKUP) {
        dropdown.hide();
        return;
      }
      if (element.value in cache) {
        cache[element.value].forEach(child => {
          dropdownElement.appendChild(child);
        });
        dropdown.show();
        return;
      }
      ISLE.fetch(`method=autocomplete&field=${element.dataset['autocomplete']}&term=${encodeURIComponent(element.value)}`).then(data => {
        const results = data.results;
        if (results.length == 0) {
          dropdown.hide();
          return;
        }
        dropdownElement.innerHTML = '';
        results.forEach(result => {
          const item = {
            label: result.label ?? (result.value ?? result),
            value: result.value ?? result
          };
          const index = item.label.search(new RegExp('\\b' + element.value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'i'));
          const label = item.label.substring(0, index)
            + `<span class="fw-bold">${item.label.substring(index, index + element.value.length)}</span>`
            + item.label.substring(index + element.value.length);
          const dropdownItem = ISLE.template(`<button type="button" class="dropdown-item" data-value="${item.value}">${label}</button>`);
          dropdownItem.addEventListener('click', event => {
            element.value = ISLE.template(`<p>${item.label}</p>`).innerText;
            if (element.dataset.hasOwnProperty('target')) {
              const target = document.getElementById(element.dataset.target)
              target.value = item.value;
              target.dispatchEvent(new Event('change'));
            }
            dropdown.hide();
          });
          dropdownElement.appendChild(dropdownItem);
        });
        cache[element.value] = dropdownElement.childNodes;
        dropdown.show();
      });
    });
  }

  static error(error) {
      // TODO: Bootstrap can't handle multiple modals/offcanvas, so just remove them for now
      document.querySelectorAll('.modal-backdrop, .modal, .offcanvas').forEach(element => {
        element.remove();
      });
      const modalElement = ISLE.template(`<div class="modal fade" data-bs-backdrop="static" aria-labelledby="#modal-title">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header alert-danger">
        <h5 class="modal-title">Error</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        ${error}
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>`);
      document.querySelector('body').append(modalElement);
      const modal = new bootstrap.Modal(modalElement);
      modalElement.addEventListener('hidden.bs.modal', event => {
        modalElement.remove();
      });
      modal.show();
      return Promise.reject(error);
  }

  static fetch(queryString, init = {}) {
    return fetch(`${window['web_root']}/ajax?${queryString}`, init).then(response => {
      // Handle bad responses
      if (response.ok) {
        return response.text();
      }
      throw `${response.status}: ${response.statusText}`;
    }).then(text => {
      // response.json() errors aren't caught with catch(), so parse with JSON.parse()
      try {
        return JSON.parse(text);
      } catch (error) {
        throw text;
      }
    }).then(data => {
      if (data.hasOwnProperty('error')) {
        throw data.error;
      }
      return data;
    }).catch(ISLE.error);
  }

  static fetchHtml(queryString) {
    return this.fetch(`method=html&html=${queryString}`).then(data => {
      const element = this.template(data.html);
      return element;
    });
  }

  static form(queryString) {
    return this.appendFetch(queryString).then(dialog => {
      // Use modal footer buttons, which are not inside the form
      const form = dialog.querySelector('form');
      dialog.querySelector('.modal-footer button[type="submit"]').addEventListener('click', event => {
        if (form.checkValidity()) {
          form.submit();
        }
        form.classList.add('was-validated');
      });
      const confirmDelete = dialog.querySelector('.modal-footer .text-danger');
      if (confirmDelete) {
        confirmDelete.addEventListener('click', event => {
          form.action += '?delete=1';
          form.submit();
        });
      }
      const modal = new bootstrap.Modal(dialog);
      dialog.addEventListener('hide.bs.modal', event => {
        document.querySelectorAll('.suggestions').forEach(element => {
          element.classList.add('d-none');
        });
      });
      dialog.addEventListener('hidden.bs.modal', event => {
        dialog.remove();
      });
      modal.show();
    });
  }

  static showCart(cart) {
    this.appendFetch(cart).then(element => {
      const offcanvas = new bootstrap.Offcanvas(element);
      const submitButton = element.querySelector('[data-transact]');
      submitButton.addEventListener('click', event => {
        offcanvas.dispose();
        element.remove();
      });
      element.querySelectorAll('[data-id] [data-role="remove"]').forEach(element => {
        element.addEventListener('click', event => {
          const assetElement = element.closest('[data-id]');
          const assetId = assetElement.dataset.id;
          this.updateCart(cart, `action=remove&asset=${assetId}`).then(count => {
            document.querySelectorAll(`[data-id="${assetId}"] [data-role="remove"]`).forEach(element => {
              element.classList.add('d-none');
            });
            document.querySelectorAll(`[data-id="${assetId}"] [data-role="add"]`).forEach(element => {
              element.classList.remove('d-none');
            });
            assetElement.remove();
            if (count == 0) {
              offcanvas.hide();
            }
          });
        });
      });
      element.querySelector('[data-role="empty"]').addEventListener('click', event => {
        this.updateCart(cart, 'action=empty').then(count => {
          if (count == 0) {
            offcanvas.hide();
          }
        });
      });
      element.addEventListener('hidden.bs.offcanvas', event => {
        element.remove();
      });
      offcanvas.show();
    });
  }

  static template(html) {
    const template = document.createElement('template');
    template.innerHTML = html;
    this.addEventListeners(template.content);
    return template.content.firstChild;
  }

  static updateCart(cart, queryString) {
    return this.fetch(`method=${cart}&${queryString}`).then(data => {
      const button = document.querySelector(`[data-cart=${cart}]:not([data-role])`);
      button.disabled = data.count == 0;
      const badge = button.querySelector('.badge');
      badge.innerHTML = data.count;
      return data.count;
    });
  }

}

document.addEventListener('DOMContentLoaded', event => {

  /** Model editor launchers */
  document.querySelectorAll('[data-node] [data-id] [data-role="edit"], [data-node][data-id][data-role="edit"]').forEach(element => {
    element.addEventListener('click', event => {
      const form = element.closest('[data-node]').dataset.node + 'Form';
      const id = element.closest('[data-id]').dataset.id;
      ISLE.form(`${form}&id=${id}`);
    });
  });

  ISLE.addEventListeners(document);

  /** Special cases that have custom resolve handlers */

  document.querySelectorAll('[data-id] [data-cart][data-role="add"]').forEach((element) => {
    element.addEventListener('click', event => {
      const cart = element.dataset.cart;
      const assetId = element.closest('[data-id]').dataset.id;
      ISLE.updateCart(cart, `action=add&asset=${assetId}`).then(count => {
        element.classList.add('d-none');
        element.closest('[data-id]').querySelector('[data-role="remove"]').classList.remove('d-none');
      });
    });
  });

  document.querySelectorAll('[data-id] [data-cart][data-role="remove"]').forEach(element => {
    element.addEventListener('click', event => {
      const cart = element.dataset.cart;
      const assetId = element.closest('[data-id]').dataset.id;
      ISLE.updateCart(cart, `action=remove&asset=${assetId}`).then(count => {
        element.classList.add('d-none');
        element.closest('[data-id]').querySelector('[data-role="add"]').classList.remove('d-none');
      });
    });
  });

  document.querySelectorAll('#filters .col-auto #attribute').forEach(element => {
    element.addEventListener('change', event => {
      const adder = document.querySelector('[data-autocomplete="attributes"][data-target="attribute"]');
      ISLE.fetchHtml(`attributeSelects&attributes[]=${element.value}`).then(newElement => {
        element.closest('.col-auto').before(newElement);
        adder.value = '';
      });
    });
  });

  document.querySelectorAll('[data-sql="export"]').forEach(element => {
    element.addEventListener('click', event => {
      ISLE.fetch('method=sql&action=export').then(data => {
        const a = document.createElement('a');
        a.setAttribute('href', 'data:application/sql,' + encodeURIComponent(data.sql));
        a.setAttribute('download', 'data.sql');
        a.style.display = 'none';
        document.body.append(a);
        a.click();
        a.remove();
      });
    });
  });

});
