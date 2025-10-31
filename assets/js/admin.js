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
  deleteDoc,
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
  getDownloadURL,
  deleteObject
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
const playersTableBody = document.getElementById('players-table-body');
const playerDetail = document.getElementById('player-detail');
const playerDetailName = document.getElementById('player-detail-name');
const playerDetailSubtitle = document.getElementById('player-detail-subtitle');
const playerDetailList = document.getElementById('player-detail-list');
const playerDetailClose = document.getElementById('player-detail-close');

let challengesUnsubscribe = null;
let playersUnsubscribe = null;
let editingChallengeId = null;
let playersCache = [];
let activeDetailPlayerId = null;

const challengeMap = new Map();

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

playerDetailClose?.addEventListener('click', () => {
  hidePlayerDetail();
});

playersTableBody?.addEventListener('click', async event => {
  const target = event.target.closest('button');
  if (!target) return;
  const playerId = target.dataset.playerId;
  if (!playerId) return;

  if (target.classList.contains('js-view-player')) {
    showPlayerDetail(playerId);
    return;
  }

  if (target.classList.contains('js-reset-player')) {
    await handleResetPlayer(playerId);
  }
});

scoreboardList?.addEventListener('click', event => {
  const item = event.target.closest('li');
  if (!item) return;
  const playerId = item.dataset.playerId;
  if (playerId) {
    showPlayerDetail(playerId);
  }
});

document.addEventListener('keydown', event => {
  if (event.key === 'Escape') {
    hidePlayerDetail();
  }
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
    if (activeDetailPlayerId) {
      // Vuelve a pintar el detalle con la información más reciente de las pruebas.
      showPlayerDetail(activeDetailPlayerId);
    }
  });
}

function renderChallengeList(items) {
  challengeList.innerHTML = '';
  challengeMap.clear();
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
      <div class="challenge-list__info">
        <span class="challenge-list__title">${item.title}</span>
        <div class="challenge-list__meta">
          <span>Respuesta: ${item.answer || '—'}</span>
          <span>QR: ${item.qrCodeValue || '—'}</span>
        </div>
      </div>
      <div class="challenge-list__actions">
        <button type="button" class="btn-icon js-edit-challenge" aria-label="Editar ${item.title}">Editar</button>
        <button type="button" class="btn-icon btn-icon--danger js-delete-challenge" aria-label="Eliminar ${item.title}">Eliminar</button>
      </div>
    `;
    li.addEventListener('click', () => populateForm(item));
    li.querySelector('.js-edit-challenge').addEventListener('click', event => {
      event.stopPropagation();
      populateForm(item);
    });
    li.querySelector('.js-delete-challenge').addEventListener('click', async event => {
      event.stopPropagation();
      await handleDeleteChallenge(item);
    });
    challengeMap.set(item.id, item);
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

  playersCache = sorted;

  scoreboardList.innerHTML = '';
  sorted.forEach(player => {
    const li = document.createElement('li');
    li.className = 'scoreboard__item';
    li.dataset.playerId = player.id;
    const completedChallenges = (player.completedChallenges || []).length;
    const assigned = (player.assignedChallenges || []).length || '0';
    li.innerHTML = `
      <span>${player.name || 'Participante'}</span>
      <span>${player.score || 0} pts · ${completedChallenges}/${assigned} pruebas</span>
    `;
    scoreboardList.appendChild(li);
  });

  renderPlayersTable(sorted);

  if (activeDetailPlayerId) {
    showPlayerDetail(activeDetailPlayerId);
  }
}

function renderPlayersTable(players) {
  if (!playersTableBody) return;
  playersTableBody.innerHTML = '';

  if (!players.length) {
    const emptyRow = document.createElement('tr');
    emptyRow.innerHTML = '<td colspan="5" class="table__empty">Aún no hay participantes registrados.</td>';
    playersTableBody.appendChild(emptyRow);
    hidePlayerDetail();
    return;
  }

  players.forEach(player => {
    const completedChallenges = player.completedChallenges ? player.completedChallenges.length : 0;
    const assigned = player.assignedChallenges ? player.assignedChallenges.length : 0;
    const lastUpdate = formatTimestamp(player.lastUpdate);
    const row = document.createElement('tr');
    row.innerHTML = `
      <td data-label="Participante">
        <button type="button" class="link-button js-view-player" data-player-id="${player.id}">
          ${player.name || 'Participante'}
        </button>
      </td>
      <td data-label="Puntos">${player.score || 0}</td>
      <td data-label="Pruebas superadas">${completedChallenges}/${assigned}</td>
      <td data-label="Última actualización">${lastUpdate}</td>
      <td data-label="Acciones" class="table__actions">
        <div class="table__actions-wrapper">
          <button type="button" class="btn btn--ghost btn--small js-view-player" data-player-id="${player.id}">Ver detalle</button>
          <button type="button" class="btn btn--danger btn--small js-reset-player" data-player-id="${player.id}">Reiniciar</button>
        </div>
      </td>
    `;
    playersTableBody.appendChild(row);
  });
}

async function handleDeleteChallenge(item) {
  const confirmed = confirm(`¿Seguro que quieres eliminar la prueba "${item.title}"? Esta acción no se puede deshacer.`);
  if (!confirmed) return;
  try {
    await deleteDoc(doc(db, 'challenges', item.id));
    if (item.imagePath) {
      try {
        const storageRef = ref(storage, item.imagePath);
        await deleteObject(storageRef);
      } catch (storageError) {
        console.warn('No se pudo eliminar la imagen de la prueba en Storage', storageError);
      }
    }
  } catch (error) {
    console.error('No se pudo eliminar la prueba', error);
    alert('Ocurrió un error al eliminar la prueba. Intenta de nuevo.');
  }
}

async function handleResetPlayer(playerId) {
  const player = playersCache.find(candidate => candidate.id === playerId);
  const playerName = player?.name || 'este participante';
  const confirmed = confirm(`¿Reiniciar el progreso de ${playerName}? Se borrarán sus puntos y pruebas completadas.`);
  if (!confirmed) return;

  try {
    await setDoc(
      doc(db, 'players', playerId),
      {
        score: 0,
        completedChallenges: [],
        assignedChallenges: [],
        lastUpdate: serverTimestamp()
      },
      { merge: true }
    );
    alert('Progreso reiniciado. El participante recibirá un nuevo conjunto de pruebas cuando vuelva a iniciar sesión.');
  } catch (error) {
    console.error('No se pudo reiniciar el progreso del participante', error);
    alert('No se pudo reiniciar el progreso. Intenta nuevamente.');
  }
}

function showPlayerDetail(playerId) {
  if (!playerDetail || !playerDetailList) return;
  const player = playersCache.find(candidate => candidate.id === playerId);
  if (!player) {
    hidePlayerDetail();
    return;
  }

  activeDetailPlayerId = playerId;
  playerDetail.classList.remove('hidden');
  playerDetailName.textContent = player.name || 'Participante';

  const completedSet = new Set(player.completedChallenges || []);
  const assigned = player.assignedChallenges && player.assignedChallenges.length
    ? player.assignedChallenges
    : Array.from(challengeMap.keys());
  const total = assigned.length;
  const completed = completedSet.size;
  const progressPercentage = total ? Math.round((completed / total) * 100) : 0;
  playerDetailSubtitle.textContent = `${completed} de ${total || '0'} pruebas resueltas (${progressPercentage}% de avance)`;

  playerDetailList.innerHTML = '';

  if (!assigned.length) {
    const item = document.createElement('li');
    item.className = 'player-detail__item';
    item.textContent = 'Aún no tiene pruebas asignadas.';
    playerDetailList.appendChild(item);
    return;
  }

  assigned.forEach((challengeId, index) => {
    const challenge = challengeMap.get(challengeId);
    const li = document.createElement('li');
    li.className = 'player-detail__item';
    if (completedSet.has(challengeId)) {
      li.classList.add('is-completed');
    }
    const position = String(index + 1).padStart(2, '0');
    const title = challenge?.title || 'Prueba eliminada';
    li.innerHTML = `
      <span class="player-detail__index">${position}</span>
      <div class="player-detail__content">
        <strong>${title}</strong>
        <small>${challenge?.announcement || 'Sin anunciado disponible.'}</small>
      </div>
    `;
    playerDetailList.appendChild(li);
  });
}

function hidePlayerDetail() {
  if (!playerDetail) return;
  playerDetail.classList.add('hidden');
  playerDetailList.innerHTML = '';
  playerDetailName.textContent = '';
  playerDetailSubtitle.textContent = '';
  activeDetailPlayerId = null;
}

function formatTimestamp(timestamp) {
  if (!timestamp?.toDate) {
    return '—';
  }
  try {
    const date = timestamp.toDate();
    return new Intl.DateTimeFormat('es-ES', {
      day: '2-digit',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit'
    }).format(date);
  } catch (error) {
    console.warn('No se pudo formatear la fecha de actualización', error);
    return '—';
  }
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
