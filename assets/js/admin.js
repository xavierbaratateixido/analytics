import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.12.0/firebase-app.js';
import {
  getAuth,
  signInWithEmailAndPassword,
  signOut,
  onAuthStateChanged
} from 'https://www.gstatic.com/firebasejs/10.12.0/firebase-auth.js';
import {
  getFirestore,
  addDoc,
  collection,
  doc,
  onSnapshot,
  orderBy,
  query,
  serverTimestamp,
  setDoc
} from 'https://www.gstatic.com/firebasejs/10.12.0/firebase-firestore.js';
import {
  getStorage,
  ref,
  uploadBytes,
  getDownloadURL
} from 'https://www.gstatic.com/firebasejs/10.12.0/firebase-storage.js';

const firebaseConfig = {
  apiKey: 'AIzaSyC4xJNQoZPXA24XYxJdQUTP0Zx-f0J4LTY',
  authDomain: 'halloween-de698.firebaseapp.com',
  projectId: 'halloween-de698',
  storageBucket: 'halloween-de698.firebasestorage.app',
  messagingSenderId: '613913337559',
  appId: '1:613913337559:web:30bfa9e4bae03beb5ada92',
  measurementId: 'G-06E6JCKWFE'
};

// Actualiza esta lista con los correos autorizados para administrar la jincana.
const ALLOWED_ADMIN_EMAILS = ['maestro@halloween.com'];

const app = initializeApp(firebaseConfig);
const auth = getAuth(app);
const db = getFirestore(app);
const storage = getStorage(app);

const adminAuthSection = document.getElementById('admin-auth');
const adminPanel = document.getElementById('admin-panel');
const adminLoginForm = document.getElementById('admin-login-form');
const adminLogoutBtn = document.getElementById('admin-logout');
const challengeForm = document.getElementById('challenge-form');
const resetFormBtn = document.getElementById('reset-form');
const challengeList = document.getElementById('admin-challenge-list');
const scoreboardList = document.getElementById('admin-scoreboard');
const metricParticipants = document.getElementById('metric-participants');
const metricTotalPoints = document.getElementById('metric-total-points');
const metricCompleted = document.getElementById('metric-completed');
const metricAverage = document.getElementById('metric-average');

let challengesUnsubscribe = null;
let playersUnsubscribe = null;
let editingChallengeId = null;

adminLoginForm?.addEventListener('submit', async event => {
  event.preventDefault();
  const email = document.getElementById('admin-email').value.trim();
  const password = document.getElementById('admin-password').value;
  try {
    await signInWithEmailAndPassword(auth, email, password);
  } catch (error) {
    console.error('No se pudo iniciar sesión', error);
    alert('Acceso denegado: ' + (error.message || 'Comprueba tu contraseña.'));
  }
});

adminLogoutBtn?.addEventListener('click', () => {
  signOut(auth);
});

resetFormBtn?.addEventListener('click', () => {
  challengeForm.reset();
  editingChallengeId = null;
  document.getElementById('challenge-id').value = '';
});

challengeForm?.addEventListener('submit', async event => {
  event.preventDefault();
  const data = await buildChallengePayload();
  if (!data) return;
  try {
    if (editingChallengeId) {
      const challengeRef = doc(db, 'challenges', editingChallengeId);
      await setDoc(challengeRef, { ...data, updatedAt: serverTimestamp() }, { merge: true });
      alert('Prueba actualizada correctamente.');
    } else {
      await addDoc(collection(db, 'challenges'), {
        ...data,
        createdAt: serverTimestamp(),
        updatedAt: serverTimestamp()
      });
      alert('Prueba creada.');
    }
    challengeForm.reset();
    editingChallengeId = null;
    document.getElementById('challenge-id').value = '';
  } catch (error) {
    console.error('No se pudo guardar la prueba', error);
    alert('No se pudo guardar la prueba: ' + (error.message || 'Intenta de nuevo.'));
  }
});

async function buildChallengePayload() {
  const title = document.getElementById('challenge-title').value.trim();
  const description = document.getElementById('challenge-description').value.trim();
  const announcement = document.getElementById('challenge-announcement').value.trim();
  const answer = document.getElementById('challenge-answer').value.trim();
  const qrValue = document.getElementById('challenge-qr-value').value.trim();
  const qrHint = document.getElementById('challenge-qr-hint').value.trim();
  const imageFile = document.getElementById('challenge-image').files?.[0];
  const imageUrlInput = document.getElementById('challenge-image-url').value.trim();

  if (!title || !description || !announcement || !answer || !qrValue || !qrHint) {
    alert('Completa todos los campos obligatorios.');
    return null;
  }

  let uploadedPath = null;
  let finalImageUrl = imageUrlInput || null;

  if (imageFile) {
    const safeName = `${Date.now()}-${imageFile.name}`.replace(/\s+/g, '-');
    const storageRef = ref(storage, `challenges/${safeName}`);
    try {
      await uploadBytes(storageRef, imageFile);
      uploadedPath = storageRef.fullPath;
      finalImageUrl = await getDownloadURL(storageRef);
    } catch (error) {
      console.error('No se pudo subir la imagen', error);
      alert('La imagen no se pudo subir. Inténtalo de nuevo.');
      return null;
    }
  }

  return {
    title,
    description,
    announcement,
    answer,
    qrCodeValue: qrValue,
    qrHint,
    imageUrl: finalImageUrl,
    imagePath: uploadedPath || null
  };
}

function subscribeToChallenges() {
  if (challengesUnsubscribe) {
    challengesUnsubscribe();
  }
  const q = query(collection(db, 'challenges'), orderBy('createdAt', 'desc'));
  challengesUnsubscribe = onSnapshot(q, snapshot => {
    const items = snapshot.docs.map(docSnap => ({ id: docSnap.id, ...docSnap.data() }));
    renderChallengeList(items);
  });
}

function renderChallengeList(items) {
  challengeList.innerHTML = '';
  if (!items.length) {
    const empty = document.createElement('li');
    empty.textContent = 'Aún no hay pruebas creadas.';
    empty.className = 'challenge-list__item';
    challengeList.appendChild(empty);
    return;
  }

  items.forEach(item => {
    const li = document.createElement('li');
    li.className = 'challenge-list__item';
    li.dataset.id = item.id;
    li.innerHTML = `
      <span class="challenge-list__title">${item.title}</span>
      <div class="challenge-list__meta">
        <span>Respuesta: ${item.answer || '—'}</span>
        <span>QR: ${item.qrCodeValue || '—'}</span>
      </div>
    `;
    li.addEventListener('click', () => populateForm(item));
    challengeList.appendChild(li);
  });
}

function populateForm(item) {
  editingChallengeId = item.id;
  document.getElementById('challenge-id').value = item.id;
  document.getElementById('challenge-title').value = item.title || '';
  document.getElementById('challenge-description').value = item.description || '';
  document.getElementById('challenge-announcement').value = item.announcement || '';
  document.getElementById('challenge-answer').value = item.answer || '';
  document.getElementById('challenge-qr-value').value = item.qrCodeValue || '';
  document.getElementById('challenge-qr-hint').value = item.qrHint || '';
  document.getElementById('challenge-image-url').value = item.imageUrl || '';
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function subscribeToPlayers() {
  if (playersUnsubscribe) {
    playersUnsubscribe();
  }
  playersUnsubscribe = onSnapshot(collection(db, 'players'), snapshot => {
    const players = snapshot.docs.map(docSnap => ({ id: docSnap.id, ...docSnap.data() }));
    renderPlayersAnalytics(players);
  });
}

function renderPlayersAnalytics(players) {
  const totalParticipants = players.length;
  const totalPoints = players.reduce((acc, player) => acc + (player.score || 0), 0);
  const completed = players.reduce((acc, player) => acc + ((player.completedChallenges || []).length), 0);
  const average = totalParticipants ? (totalPoints / totalParticipants).toFixed(1) : '0';

  metricParticipants.textContent = totalParticipants;
  metricTotalPoints.textContent = totalPoints;
  metricCompleted.textContent = completed;
  metricAverage.textContent = average;

  const sorted = [...players].sort((a, b) => {
    const scoreDiff = (b.score || 0) - (a.score || 0);
    if (scoreDiff !== 0) return scoreDiff;
    const aTime = a.lastUpdate?.toMillis?.() || 0;
    const bTime = b.lastUpdate?.toMillis?.() || 0;
    return aTime - bTime;
  });

  scoreboardList.innerHTML = '';
  sorted.forEach(player => {
    const li = document.createElement('li');
    li.className = 'scoreboard__item';
    li.innerHTML = `<span>${player.name || 'Participante'}</span><span>${player.score || 0} pts</span>`;
    scoreboardList.appendChild(li);
  });
}

function toggleAdminUI(isAdmin) {
  if (isAdmin) {
    adminAuthSection.classList.add('hidden');
    adminPanel.classList.remove('hidden');
  } else {
    adminAuthSection.classList.remove('hidden');
    adminPanel.classList.add('hidden');
  }
}

onAuthStateChanged(auth, user => {
  if (!user) {
    toggleAdminUI(false);
    if (challengesUnsubscribe) challengesUnsubscribe();
    if (playersUnsubscribe) playersUnsubscribe();
    return;
  }

  if (!ALLOWED_ADMIN_EMAILS.includes(user.email || '')) {
    alert('No tienes permisos para acceder al panel de administración.');
    signOut(auth);
    return;
  }

  toggleAdminUI(true);
  subscribeToChallenges();
  subscribeToPlayers();
});

window.addEventListener('beforeunload', () => {
  if (challengesUnsubscribe) challengesUnsubscribe();
  if (playersUnsubscribe) playersUnsubscribe();
});
