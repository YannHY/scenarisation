// Transfer a selected file between pages locally, without uploading it.
(() => {
  async function transaction(mode, operation) {
    const db = await new Promise((resolve, reject) => {
      const request = indexedDB.open('learningDesignerFileTransfer', 1);
      request.onupgradeneeded = () => request.result.createObjectStore('files');
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
    try {
      return await new Promise((resolve, reject) => {
        const tx = db.transaction('files', mode);
        const result = operation(tx.objectStore('files'));
        tx.oncomplete = () => resolve(result?.result);
        tx.onerror = () => reject(tx.error);
        tx.onabort = () => reject(tx.error);
      });
    } finally { db.close(); }
  }
  window.learningDesignerFileTransfer = {
    async store(file) {
      const id = crypto.randomUUID();
      await transaction('readwrite', store => store.put(file, id));
      return id;
    },
    async take(id) {
      const file = await transaction('readonly', store => store.get(id));
      await transaction('readwrite', store => store.delete(id));
      return file;
    }
  };
})();
