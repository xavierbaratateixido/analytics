import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.12.0/firebase-app.js';
import {
  getAuth,
  signInWithEmailAndPassword,
  createUserWithEmailAndPassword,
  signOut,
  updateProfile,
  onAuthStateChanged
} from 'https://www.gstatic.com/firebasejs/10.12.0/firebase-auth.js';
import {
  getFirestore,
  collection,
  doc,
  getDocs,
  getDoc,
  onSnapshot,
  query,
  orderBy,
  setDoc,
  updateDoc,
  serverTimestamp,
  arrayUnion,
  increment
} from 'https://www.gstatic.com/firebasejs/10.12.0/firebase-firestore.js';
import { getStorage, ref, getDownloadURL } from 'https://www.gstatic.com/firebasejs/10.12.0/firebase-storage.js';

const firebaseConfig = {
  apiKey: 'AIzaSyC4xJNQoZPXA24XYxJdQUTP0Zx-f0J4LTY',
  authDomain: 'halloween-de698.firebaseapp.com',
  projectId: 'halloween-de698',
  storageBucket: 'halloween-de698.firebasestorage.app',
  messagingSenderId: '613913337559',
  appId: '1:613913337559:web:30bfa9e4bae03beb5ada92',
  measurementId: 'G-06E6JCKWFE'
};

const app = initializeApp(firebaseConfig);
const auth = getAuth(app);
const db = getFirestore(app);
const storage = getStorage(app);

const html5QrElementId = 'scanner';
let qrCodeScanner;
let activeChallenge = null;
let activeChallengeCard = null;
let playerUnsubscribe = null;
let scoreboardUnsubscribe = null;
let challengeCache = [];
let allChallengesCache = [];
let assignedChallengeIds = [];

const authPanel = document.getElementById('auth-panel');
const loginForm = document.getElementById('login-form');
const registerForm = document.getElementById('register-form');
const showRegisterBtn = document.getElementById('show-register');
const huntArea = document.getElementById('hunt-area');
const playerNameEl = document.getElementById('player-name');
const playerScoreEl = document.getElementById('player-score');
const totalChallengesEl = document.getElementById('total-challenges');
const challengeGrid = document.getElementById('challenge-grid');
const logoutBtn = document.getElementById('logout-btn');
const scoreboardList = document.getElementById('scoreboard-list');

const scannerModal = document.getElementById('scanner-modal');
const scannerMessage = document.getElementById('scanner-message');
const closeScannerBtn = document.getElementById('close-scanner');

showRegisterBtn?.addEventListener('click', () => {
  registerForm.classList.toggle('form--hidden');
  showRegisterBtn.textContent = registerForm.classList.contains('form--hidden')
    ? 'Regístrate aquí'
    : 'Ya tengo cuenta';
});

registerForm?.addEventListener('submit', async event => {
  event.preventDefault();
  const name = document.getElementById('register-name').value.trim();
  const email = document.getElementById('register-email').value.trim();
  const password = document.getElementById('register-password').value;
  if (!name) {
    alert('Introduce el nombre del equipo o participante.');
    return;
  }
  try {
    const credential = await createUserWithEmailAndPassword(auth, email, password);
    if (credential.user) {
      await updateProfile(credential.user, { displayName: name });
      await setDoc(
        doc(db, 'players', credential.user.uid),
        {
          name,
          email,
          score: 0,
          completedChallenges: [],
          createdAt: serverTimestamp(),
          lastUpdate: serverTimestamp()
        },
        { merge: true }
      );
      registerForm.reset();
      registerForm.classList.add('form--hidden');
      showRegisterBtn.textContent = 'Regístrate aquí';
      alert('Registro completo. Ahora puedes iniciar sesión.');
    }
  } catch (error) {
    console.error('Error al registrar', error);
    alert('No se pudo crear el usuario: ' + (error.message || 'Error desconocido'));
  }
});

loginForm?.addEventListener('submit', async event => {
  event.preventDefault();
  const email = document.getElementById('login-email').value.trim();
  const password = document.getElementById('login-password').value;
  try {
    await signInWithEmailAndPassword(auth, email, password);
  } catch (error) {
    console.error('Error de inicio de sesión', error);
    alert('No se pudo iniciar sesión: ' + (error.message || 'Comprueba tus credenciales.'));
  }
});

logoutBtn?.addEventListener('click', () => {
  signOut(auth);
});

closeScannerBtn?.addEventListener('click', () => {
  stopScanner();
  hideScanner();
});

function showScanner(card, challenge) {
  activeChallenge = challenge;
  activeChallengeCard = card;
  scannerModal.classList.remove('hidden');
  scannerMessage.textContent = 'Enfoca el código QR para revelar la pista oculta...';
  startScanner();
}

function hideScanner() {
  scannerModal.classList.add('hidden');
  scannerMessage.textContent = '';
  activeChallenge = null;
  activeChallengeCard = null;
}

async function startScanner() {
  try {
    if (!qrCodeScanner) {
      qrCodeScanner = new Html5Qrcode(html5QrElementId);
    }
    await qrCodeScanner.start(
      { facingMode: 'environment' },
      { fps: 10, qrbox: { width: 240, height: 240 } },
      onQrSuccess,
      onQrError
    );
  } catch (error) {
    console.error('No se pudo iniciar el escáner', error);
    scannerMessage.textContent = 'No se pudo acceder a la cámara. Permite el uso de la cámara e inténtalo de nuevo.';
  }
}

function stopScanner() {
  if (qrCodeScanner) {
    qrCodeScanner
      .stop()
      .catch(error => console.warn('Error al detener el escáner', error))
      .finally(() => {
        qrCodeScanner.clear();
        qrCodeScanner = null;
      });
  }
}

function onQrError(errorMessage) {
  console.debug('QR error', errorMessage);
}

function onQrSuccess(decodedText) {
  if (!activeChallenge || !activeChallengeCard) {
    return;
  }
  const expected = (activeChallenge.qrCodeValue || '').trim();
  const sanitized = decodedText.trim();
  if (!expected) {
    revealHint(activeChallengeCard, activeChallenge.qrHint || 'Pista desbloqueada.');
    stopScanner();
    scannerMessage.textContent = 'Pista revelada';
    setTimeout(() => hideScanner(), 1200);
    return;
  }
  if (sanitized.toLowerCase() === expected.toLowerCase()) {
    revealHint(activeChallengeCard, activeChallenge.qrHint || 'Pista revelada.');
    scannerMessage.textContent = '¡Pista desbloqueada!';
    stopScanner();
    setTimeout(() => hideScanner(), 1200);
  } else {
    scannerMessage.textContent = 'Este código no corresponde a la prueba. Sigue buscando...';
  }
}

function revealHint(card, hint) {
  const hintEl = card.querySelector('.challenge-card__hint');
  if (!hintEl) return;
  hintEl.textContent = hint;
  hintEl.classList.remove('challenge-card__hint--hidden');
}

function shuffle(array) {
  return array
    .map(value => ({ value, sort: Math.random() }))
    .sort((a, b) => a.sort - b.sort)
    .map(({ value }) => value);
}

function sanitizeAnswer(value) {
  return value.trim().toLowerCase();
}

function renderChallenges(challenges, completed = []) {
  if (!challenges.length) {
    challengeGrid.innerHTML = '<p class="challenge-grid__empty">El maestro aún no ha preparado las pruebas.</p>';
    return;
  }

  challengeGrid.innerHTML = '';
  const completedSet = new Set(completed);
  challenges.forEach((challenge, index) => {
    const template = document.getElementById('challenge-template');
    const fragment = template.content.cloneNode(true);
    const card = fragment.querySelector('.challenge-card');
    const image = fragment.querySelector('.challenge-card__image');
    const number = fragment.querySelector('.challenge-number');
    const title = fragment.querySelector('.challenge-card__title');
    const description = fragment.querySelector('.challenge-card__description');
    const announcement = fragment.querySelector('.challenge-card__announcement');
    const qrBtn = fragment.querySelector('.btn--qr');
    const submitBtn = fragment.querySelector('.btn--submit');
    const input = fragment.querySelector('.challenge-card__input');
    const status = fragment.querySelector('.challenge-card__status');
    const hint = fragment.querySelector('.challenge-card__hint');

    card.dataset.challengeId = challenge.id;
    number.textContent = String(index + 1).padStart(2, '0');
    title.textContent = challenge.title;
    description.textContent = challenge.description;
    announcement.textContent = challenge.announcement;

    image.src = 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=900&q=80';
    image.alt = 'Participante misterioso';
    resolveChallengeImage(challenge, image);

    if (challenge.qrHint) {
      hint.textContent = challenge.qrHint;
      hint.classList.add('challenge-card__hint--hidden');
    }

    qrBtn.addEventListener('click', () => {
      showScanner(card, challenge);
    });

    submitBtn.addEventListener('click', async () => {
      const answer = sanitizeAnswer(input.value);
      if (!answer) {
        status.textContent = 'Introduce tu respuesta antes de enviar.';
        status.style.color = 'var(--color-muted)';
        return;
      }
      const correctAnswer = sanitizeAnswer(challenge.answer || '');
      if (!correctAnswer) {
        status.textContent = 'Esta prueba aún no tiene respuesta configurada.';
        status.style.color = 'var(--color-error)';
        return;
      }
      if (completedSet.has(challenge.id)) {
        status.textContent = 'Ya superaste esta prueba. ¡Continúa con las demás!';
        status.style.color = 'var(--color-secondary)';
        return;
      }
      if (answer === correctAnswer) {
        try {
          await markChallengeCompleted(challenge.id);
          status.textContent = '¡Respuesta correcta! Sumaste un punto.';
          status.style.color = 'var(--color-success)';
          card.classList.add('is-completed');
          completedSet.add(challenge.id);
        } catch (error) {
          console.error('No se pudo registrar el punto', error);
          status.textContent = 'No se pudo registrar el punto. Reintenta en unos segundos.';
          status.style.color = 'var(--color-error)';
        }
      } else {
        status.textContent = 'Respuesta incorrecta... ¡El terror continúa!';
        status.style.color = 'var(--color-error)';
      }
    });

    if (completedSet.has(challenge.id)) {
      card.classList.add('is-completed');
      status.textContent = 'Prueba superada.';
      status.style.color = 'var(--color-success)';
    }

    challengeGrid.appendChild(fragment);
  });
}

async function resolveChallengeImage(challenge, imageElement) {
  try {
    if (challenge.imageUrl) {
      imageElement.src = challenge.imageUrl;
      imageElement.alt = `Participante: ${challenge.title}`;
      return;
    }
    if (challenge.imagePath) {
      const imageRef = ref(storage, challenge.imagePath);
      const url = await getDownloadURL(imageRef);
      imageElement.src = url;
      imageElement.alt = `Participante: ${challenge.title}`;
    }
  } catch (error) {
    console.warn('No se pudo cargar la imagen de la prueba', challenge.id, error);
  }
}

async function ensurePlayerDocument(user) {
  if (!user) return null;
  const playerRef = doc(db, 'players', user.uid);
  const snapshot = await getDoc(playerRef);
  if (!snapshot.exists()) {
    const initialData = {
      name: user.displayName || user.email,
      email: user.email,
      score: 0,
      completedChallenges: [],
      assignedChallenges: [],
      createdAt: serverTimestamp(),
      lastUpdate: serverTimestamp()
    };
    await setDoc(playerRef, initialData);
    return { ...initialData, assignedChallenges: [] };
  }
  return snapshot.data();
}

function buildChallengeCache() {
  challengeCache = assignedChallengeIds
    .map(id => allChallengesCache.find(challenge => challenge.id === id))
    .filter(Boolean);
  totalChallengesEl.textContent = challengeCache.length;
}

async function markChallengeCompleted(challengeId) {
  const user = auth.currentUser;
  if (!user) return;
  const playerRef = doc(db, 'players', user.uid);
  await updateDoc(playerRef, {
    score: increment(1),
    completedChallenges: arrayUnion(challengeId),
    lastUpdate: serverTimestamp()
  });
}

function setupScoreboardListener(user) {
  if (scoreboardUnsubscribe) {
    scoreboardUnsubscribe();
  }
  const q = query(collection(db, 'players'), orderBy('score', 'desc'), orderBy('lastUpdate', 'asc'));
  scoreboardUnsubscribe = onSnapshot(q, snapshot => {
    const players = snapshot.docs.map(docSnap => ({ id: docSnap.id, ...docSnap.data() }));
    renderScoreboard(players, user?.uid);
  });
}

function renderScoreboard(players, currentUserId) {
  scoreboardList.innerHTML = '';
  players.forEach(player => {
    const li = document.createElement('li');
    li.className = 'scoreboard__item';
    if (player.id === currentUserId) {
      li.classList.add('is-current');
    }
    li.innerHTML = `<span>${player.name || 'Participante'}</span><span>${player.score || 0} pts</span>`;
    scoreboardList.appendChild(li);
  });
}

function arraysEqual(a = [], b = []) {
  if (a.length !== b.length) return false;
  return a.every((value, index) => value === b[index]);
}

function subscribeToPlayer(user) {
  if (playerUnsubscribe) {
    playerUnsubscribe();
  }
  if (!user) return;
  const playerRef = doc(db, 'players', user.uid);
  playerUnsubscribe = onSnapshot(playerRef, snapshot => {
    const data = snapshot.data();
    if (!data) return;
    playerNameEl.textContent = data.name || user.email;
    playerScoreEl.textContent = data.score || 0;

    if (Array.isArray(data.assignedChallenges)) {
      const sanitizedIds = data.assignedChallenges.filter(id =>
        allChallengesCache.some(challenge => challenge.id === id)
      );
      if (!arraysEqual(assignedChallengeIds, sanitizedIds)) {
        assignedChallengeIds = sanitizedIds;
        buildChallengeCache();
      }
    }

    renderChallenges(challengeCache, data.completedChallenges || []);
  });
}

async function loadChallengesForPlayer(user, playerData) {
  if (!user) return;
  const snapshot = await getDocs(collection(db, 'challenges'));
  allChallengesCache = snapshot.docs.map(docSnap => ({ id: docSnap.id, ...docSnap.data() }));

  if (!allChallengesCache.length) {
    challengeCache = [];
    assignedChallengeIds = [];
    totalChallengesEl.textContent = '0';
    challengeGrid.innerHTML = '<p class="challenge-grid__empty">El maestro aún no ha preparado las pruebas.</p>';
    subscribeToPlayer(user);
    return;
  }

  const playerRef = doc(db, 'players', user.uid);
  const basePlayerData = playerData && typeof playerData === 'object' ? playerData : {};
  let storedAssigned = Array.isArray(basePlayerData.assignedChallenges)
    ? [...basePlayerData.assignedChallenges]
    : [];
  storedAssigned = storedAssigned.filter(id => allChallengesCache.some(challenge => challenge.id === id));

  if (!storedAssigned.length) {
    const selection = shuffle([...allChallengesCache]).slice(0, Math.min(14, allChallengesCache.length));
    storedAssigned = selection.map(challenge => challenge.id);
    await setDoc(
      playerRef,
      {
        assignedChallenges: storedAssigned,
        lastUpdate: serverTimestamp()
      },
      { merge: true }
    );
  }

  const normalizedPlayerData = {
    ...basePlayerData,
    assignedChallenges: storedAssigned
  };

  assignedChallengeIds = storedAssigned;
  buildChallengeCache();
  renderChallenges(challengeCache, normalizedPlayerData.completedChallenges || []);
  subscribeToPlayer(user);
}

function toggleAuthUI(isAuthenticated) {
  if (isAuthenticated) {
    authPanel.classList.add('hidden');
    huntArea.classList.remove('hidden');
  } else {
    authPanel.classList.remove('hidden');
    huntArea.classList.add('hidden');
  }
}

onAuthStateChanged(auth, async user => {
  if (user) {
    toggleAuthUI(true);
    const playerData = await ensurePlayerDocument(user);
    setupScoreboardListener(user);
    await loadChallengesForPlayer(user, playerData);
  } else {
    toggleAuthUI(false);
    if (playerUnsubscribe) playerUnsubscribe();
    if (scoreboardUnsubscribe) scoreboardUnsubscribe();
    challengeCache = [];
    assignedChallengeIds = [];
    allChallengesCache = [];
    challengeGrid.innerHTML = '';
    scoreboardList.innerHTML = '';
    playerNameEl.textContent = '';
    playerScoreEl.textContent = '0';
  }
});

window.addEventListener('beforeunload', () => {
  stopScanner();
});
