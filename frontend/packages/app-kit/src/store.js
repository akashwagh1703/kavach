import { configureStore, createSlice } from '@reduxjs/toolkit';

const sessionSlice = createSlice({
  name: 'session',
  initialState: {
    profile: null,
    status: 'unknown',
    locale: localStorage.getItem('kavach.locale') || 'en',
  },
  reducers: {
    setProfile(state, action) {
      state.profile = action.payload;
      state.status = action.payload ? 'authenticated' : 'guest';
    },
    setLocale(state, action) {
      state.locale = action.payload;
      localStorage.setItem('kavach.locale', action.payload);
    },
  },
});

export const { setProfile, setLocale } = sessionSlice.actions;

export function createKavachStore() {
  return configureStore({ reducer: { session: sessionSlice.reducer } });
}
